import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import App from './App.vue'

const mocks = vi.hoisted(() => ({
  empresas: vi.fn(),
  empresa: vi.fn(),
  consultas: vi.fn(),
  verificar: vi.fn()
}))
vi.mock('./api', () => ({ api: mocks }))

const empresaExemplo = {
  id: 1, razao_social: 'Aurora Tecnologia Ltda.', codigo_demo: 'DEMO-EMP-001',
  cidade: 'Curitiba', uf: 'PR', situacao: 'Ativa', setor: 'Tecnologia',
  indicadores: [], socios: [], vinculos: [], registros_juridicos: []
}

function preparar(configurar?: () => void) {
  vi.resetAllMocks()
  mocks.empresas.mockResolvedValue([{ id: 1, razao_social: 'Aurora Tecnologia Ltda.', codigo_demo: 'DEMO-EMP-001' }])
  mocks.empresa.mockResolvedValue(empresaExemplo)
  mocks.consultas.mockResolvedValue([])
  mocks.verificar.mockResolvedValue({ empresa: empresaExemplo, fontes_atualizadas: 2, consulta: { id: 1, empresa_id: 1 } })
  configurar?.()
  return mount(App, { attachTo: document.body, global: {
    stubs: { ArvoreSocietaria: true, RelatorioConsolidado: true }
  } })
}

afterEach(() => { document.body.innerHTML = '' })

describe('acessibilidade da navegação principal', () => {
  it('agrupa cliques concorrentes e não registra por troca de aba ou pesquisa visual', async () => {
    const wrapper = preparar()
    await flushPromises()
    let concluir!: (value: unknown) => void
    mocks.verificar.mockImplementationOnce(() => new Promise(resolve => { concluir = resolve }))
    await wrapper.get('.company-option').trigger('click')
    await flushPromises()
    await wrapper.get('.company-option').trigger('click')
    await wrapper.get('#aba-historico').trigger('click')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(mocks.verificar).toHaveBeenCalledTimes(2)
    mocks.consultas.mockResolvedValue([{ id: 9, empresa: 'Aurora', finalidade: 'Verificação', criada_em: '2026-10-16 12:00:00' }])
    concluir({ empresa: empresaExemplo, fontes_atualizadas: 2 })
    await flushPromises()
    expect(wrapper.get('.history-row').text()).toContain('#009')
    expect(mocks.verificar).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('reutiliza a verificação pendente ao voltar à empresa e ignora perfis atrasados', async () => {
    const wrapper = preparar()
    await flushPromises()
    mocks.empresas.mockResolvedValue([empresaExemplo, { ...empresaExemplo, id: 2 }])
    mocks.empresa.mockImplementation((id: number) => Promise.resolve({ ...empresaExemplo, id }))
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    const concluir = new Map<number, (value: unknown) => void>()
    mocks.verificar.mockImplementation((id: number) => new Promise(resolve => concluir.set(id, resolve)))
    const opcoes = wrapper.findAll('.company-option')
    await opcoes[0].trigger('click')
    await flushPromises()
    await opcoes[1].trigger('click')
    await flushPromises()
    await opcoes[0].trigger('click')
    await flushPromises()
    expect(mocks.verificar).toHaveBeenCalledTimes(3)
    concluir.get(1)!({ empresa: empresaExemplo, fontes_atualizadas: 2 })
    await flushPromises()
    concluir.get(2)!({ empresa: { ...empresaExemplo, id: 2, razao_social: 'Resposta atrasada' }, fontes_atualizadas: 2 })
    await flushPromises()
    expect(wrapper.get('#titulo-empresa').text()).toBe(empresaExemplo.razao_social)
    expect(mocks.consultas).toHaveBeenCalledTimes(4)
    wrapper.unmount()
  })

  it('preserva registros e navegação quando a verificação e o histórico falham', async () => {
    const wrapper = preparar()
    // A montagem já iniciou a primeira consulta; a próxima é disparada após o POST.
    let concluir!: (value: unknown) => void
    mocks.consultas.mockImplementationOnce(() => new Promise(resolve => { concluir = resolve }))
    await flushPromises()
    await wrapper.get('.company-option').trigger('click')
    await flushPromises()
    // O clique em andamento é agrupado até o histórico terminar.
    concluir([{ id: 5, empresa: 'Aurora', finalidade: 'Verificação', criada_em: '2026-10-16 12:00:00' }])
    await flushPromises()
    mocks.consultas.mockRejectedValueOnce(new Error('offline'))
    mocks.verificar.mockRejectedValueOnce(new Error('offline'))
    await wrapper.get('.company-option').trigger('click')
    await flushPromises()
    await wrapper.get('#aba-historico').trigger('click')
    expect(wrapper.get('.history-row').text()).toContain('#005')
    expect(wrapper.text()).toContain('registros carregados foram preservados')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('ignora o histórico inicial atrasado depois de registrar uma consulta', async () => {
    let concluir!: (value: unknown) => void
    const wrapper = preparar(() => {
      mocks.consultas.mockImplementationOnce(() => new Promise(resolve => { concluir = resolve }))
      mocks.consultas.mockResolvedValue([{ id: 8, empresa: 'Aurora', finalidade: 'Verificação', criada_em: '2026-10-16 12:00:00' }])
    })
    await flushPromises()
    concluir([])
    await flushPromises()
    await wrapper.get('#aba-historico').trigger('click')
    expect(wrapper.get('.history-row').text()).toContain('#008')
    expect(mocks.verificar).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })

  it('oferece atalho ao conteúdo, campo de pesquisa com rótulo e entidade selecionada', async () => {
    const wrapper = preparar()
    await flushPromises()
    expect(wrapper.get('.skip-link').attributes('href')).toBe('#conteudo-principal')
    expect(wrapper.get('label[for="pesquisa-empresa"]').text()).toContain('empresa')
    expect(wrapper.get('input#pesquisa-empresa').attributes('type')).toBe('search')
    expect(wrapper.get('.company-option').attributes('aria-pressed')).toBe('true')
    wrapper.unmount()
  })

  it('permite alternar abas com setas, Home e End e mantém foco visível na aba ativa', async () => {
    const wrapper = preparar()
    await flushPromises()
    const geral = wrapper.get('#aba-resumo')
    expect(geral.attributes('tabindex')).toBe('0')
    await geral.trigger('keydown', { key: 'ArrowRight' })
    await flushPromises()
    expect(wrapper.get('#aba-arvore').attributes('aria-selected')).toBe('true')
    expect(wrapper.get('#aba-resumo').attributes('tabindex')).toBe('-1')
    expect(document.activeElement?.id).toBe('aba-arvore')
    await wrapper.get('#aba-arvore').trigger('keydown', { key: 'End' })
    await flushPromises()
    expect(wrapper.get('#aba-historico').attributes('aria-selected')).toBe('true')
    await wrapper.get('#aba-historico').trigger('keydown', { key: 'Home' })
    await flushPromises()
    expect(wrapper.get('#aba-resumo').attributes('aria-selected')).toBe('true')
    expect(wrapper.get('[role="tabpanel"]').attributes('aria-labelledby')).toBe('aba-resumo')
    wrapper.unmount()
  })

  it('registra verificações ao selecionar empresas, sem um botão manual de histórico', async () => {
    const wrapper = preparar()
    await flushPromises()
    expect(mocks.verificar).toHaveBeenCalledWith(1)
    expect(wrapper.find('.integration-run').exists()).toBe(false)
    expect(wrapper.find('button.register').exists()).toBe(false)
    expect(wrapper.find('#aba-integracoes').exists()).toBe(false)
    expect(wrapper.get('footer.footer').text()).toContain('fictícios')
    wrapper.unmount()
  })

  it('mantém botões HTML nativos nas ações acionáveis por teclado', async () => {
    const wrapper = preparar()
    await flushPromises()
    expect(wrapper.get('button[type="submit"]').text()).toContain('Pesquisar')
    expect(wrapper.findAll('[role="tab"]').length).toBe(6)
    expect(wrapper.get('[role="tablist"]').attributes('aria-label')).toBe('Seções da análise')
    wrapper.unmount()
  })
})

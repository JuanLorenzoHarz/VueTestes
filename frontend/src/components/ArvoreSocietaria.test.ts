import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ArvoreSocietaria from './ArvoreSocietaria.vue'

const arvore = vi.hoisted(() => vi.fn())
vi.mock('../api', () => ({ api: { arvore } }))

const mock = {
  empresa: { id: 1, codigo_demo: 'DEMO-EMP-001', razao_social: 'Aurora Tecnologia Ltda.', cidade: 'Curitiba', uf: 'PR' },
  socios: [
    { id: 1, nome: 'Marina Vale', percentual: '60.00', outras_empresas: [
      { id: 3, codigo_demo: 'DEMO-EMP-003', razao_social: 'Atlas Comercial Ltda.', cidade: 'Joinville', uf: 'SC', percentual: '45.00' }
    ] },
    { id: 2, nome: 'Lucas Prado', percentual: '40.00', outras_empresas: [] }
  ],
  origem: 'Base fictícia local'
}

describe('árvore societária', () => {
  it('mostra sócios, participação e navegação na empresa relacionada', async () => {
    arvore.mockResolvedValue(mock)
    const wrapper = mount(ArvoreSocietaria, { props: { empresaId: 1 } })
    await flushPromises()
    expect(wrapper.text()).toContain('Marina Vale')
    expect(wrapper.text()).toContain('60%')
    expect(wrapper.text()).toContain('Atlas Comercial Ltda.')
    await wrapper.find('.tree-holding-node').trigger('click')
    await wrapper.find('.tree-navigation').trigger('click')
    expect(wrapper.emitted('navegar')?.[0]).toEqual([3])
  })

  it('permite expandir e recolher os vínculos', async () => {
    arvore.mockResolvedValue(mock)
    const wrapper = mount(ArvoreSocietaria, { props: { empresaId: 1 } })
    await flushPromises()
    const expand = wrapper.find('.tree-expand')
    expect(expand.attributes('aria-expanded')).toBe('true')
    await expand.trigger('click')
    expect(wrapper.find('.tree-holding-node').exists()).toBe(false)
    expect(expand.attributes('aria-expanded')).toBe('false')
  })

  it('devolve o foco ao nó selecionado ao fechar os detalhes', async () => {
    arvore.mockResolvedValue(mock)
    const wrapper = mount(ArvoreSocietaria, { attachTo: document.body, props: { empresaId: 1 } })
    await flushPromises()
    await wrapper.get('.tree-person-node').trigger('click')
    await wrapper.get('.tree-close').trigger('click')
    await flushPromises()
    expect(document.activeElement?.classList.contains('tree-person-node')).toBe(true)
    wrapper.unmount()
  })

  it('exibe erro e opção de tentar novamente', async () => {
    arvore.mockRejectedValueOnce(new Error('Falha na API'))
    const wrapper = mount(ArvoreSocietaria, { props: { empresaId: 1 } })
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Falha na API')
    expect(wrapper.text()).toContain('Tentar novamente')
  })
})

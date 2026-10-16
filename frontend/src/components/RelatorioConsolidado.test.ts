import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import RelatorioConsolidado from './RelatorioConsolidado.vue'

const relatorio = vi.hoisted(() => vi.fn())
vi.mock('../api', () => ({ api: { relatorio } }))

const fixture = {
  referencia: 'NEXUS-0001', gerado_em: '2026-10-15T21:00:00Z', dados_ficticios: true,
  empresa: {
    razao_social: 'Aurora Tecnologia Ltda.', codigo_demo: 'DEMO-EMP-001', situacao: 'Ativa',
    setor: 'Tecnologia', cidade: 'Curitiba', uf: 'PR', socios: [{ id: 1, nome: 'Marina Vale', percentual: '60.00' }],
    vinculos: [], indicadores: [{ id: 1, fonte: 'Bureau A (simulado)', score: 740, pendencias: 1, protestos: 0, atualizado_em: '2026-09-21' }],
    registros_juridicos: []
  },
  sumario: { fontes_financeiras: 1, socios: 1, vinculos: 0, registros_juridicos: 0, registros_em_andamento: 0, fontes_com_pendencias: 1, fontes_com_protestos: 0 },
  integracao_recente: null, avisos: ['As pontuações de fontes distintas não são equivalentes.']
}

describe('relatório consolidado', () => {
  it('apresenta empresa, fonte e ressalvas sem agregar scores', async () => {
    relatorio.mockResolvedValue(fixture)
    const wrapper = mount(RelatorioConsolidado, { props: { empresaId: 1 } })
    await flushPromises()
    expect(wrapper.get('.report-print').text()).toContain('Aurora Tecnologia Ltda.')
    expect(wrapper.get('.report-print').text()).toContain('Bureau A')
    expect(wrapper.get('.report-print').text()).not.toContain('(simulado)')
    expect(wrapper.get('.print-document-footer').text()).toContain('Dados fictícios')
    expect(wrapper.get('.report-print').text()).toContain('740')
  })

  it('não imprime se ainda não foi carregado', async () => {
    relatorio.mockReturnValue(new Promise(() => {}))
    const wrapper = mount(RelatorioConsolidado, { props: { empresaId: 1 } })
    expect(wrapper.find('.report-print').exists()).toBe(false)
    const print = wrapper.findAll('button').find(b => b.text().includes('Imprimir'))!
    expect(print.attributes('disabled')).toBeDefined()
  })

  it('mostra falha de carregamento sem dados antigos', async () => {
    relatorio.mockRejectedValueOnce(new Error('Relatório indisponível'))
    const wrapper = mount(RelatorioConsolidado, { props: { empresaId: 1 } })
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Relatório indisponível')
    expect(wrapper.find('.report-print').exists()).toBe(false)
  })
})

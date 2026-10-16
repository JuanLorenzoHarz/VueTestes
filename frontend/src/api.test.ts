import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from './api'

afterEach(() => vi.unstubAllGlobals())

describe('cliente REST', () => {
  it('codifica os parâmetros da pesquisa', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ dados: [] }) })
    vi.stubGlobal('fetch', fetchMock)
    expect(await api.empresas('Aurora & Cia')).toEqual([])
    expect(fetchMock.mock.calls[0][0]).toBe('/api/empresas?busca=Aurora%20%26%20Cia')
  })

  it('inicia a verificação automaticamente por POST sem expor cenários ao usuário', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ dados: { id: 10 } }) })
    vi.stubGlobal('fetch', fetchMock)
    await api.verificar(1)
    expect(fetchMock.mock.calls[0][0]).toBe('/api/empresas/1/verificacoes')
    expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'POST' })
    expect(fetchMock.mock.calls[0][1].body).toBeUndefined()
  })

  it('mostra a mensagem retornada na falha HTTP', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 422, json: async () => ({ erro: 'Cenário inválido' }) }))
    await expect(api.verificar(1)).rejects.toThrow('Cenário inválido')
  })

  it('recupera o relatório no endpoint correto', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ dados: { referencia: 'NEXUS-0001' } }) })
    vi.stubGlobal('fetch', fetchMock)
    expect((await api.relatorio(1)).referencia).toBe('NEXUS-0001')
    expect(fetchMock.mock.calls[0][0]).toBe('/api/empresas/1/relatorio')
  })
})

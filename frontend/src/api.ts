import type { ArvoreSocietaria, Consulta, Empresa, EmpresaResumo, RelatorioConsolidado, Verificacao } from './types'

async function request<T>(path: string, options?: RequestInit): Promise<T> {
  const response = await fetch(path, {
    ...options,
    headers: { 'Content-Type': 'application/json', ...options?.headers }
  })
  const payload = await response.json()
  if (!response.ok) throw new Error(payload.erro || 'Não foi possível consultar os dados.')
  return payload.dados as T
}

export const api = {
  empresas: (busca = '') => request<EmpresaResumo[]>(`/api/empresas?busca=${encodeURIComponent(busca)}`),
  empresa: (id: number) => request<Empresa>(`/api/empresas/${id}`),
  relatorio: (id: number) => request<RelatorioConsolidado>(`/api/empresas/${id}/relatorio`),
  arvore: (id: number) => request<ArvoreSocietaria>(`/api/empresas/${id}/arvore`),
  consultas: () => request<Consulta[]>('/api/consultas'),
  verificar: (id: number) => request<Verificacao>(`/api/empresas/${id}/verificacoes`, { method: 'POST' })
}

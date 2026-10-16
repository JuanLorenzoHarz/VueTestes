export interface EmpresaResumo {
  id: number
  codigo_demo: string
  razao_social: string
  setor: string
  cidade: string
  uf: string
  situacao: string
}

export interface Socio {
  id: number
  nome: string
  percentual: string
}

export interface Vinculo {
  id: number
  tipo: string
  descricao: string
  empresa_relacionada_id: number
  empresa_relacionada: string
}

export interface Indicador {
  id: number
  fonte: string
  score: number | null
  pendencias: number
  protestos: number
  atualizado_em: string
}

export interface RegistroJuridico {
  id: number
  tipo: string
  resumo: string
  situacao: string
  fonte: string
  data_registro: string
}

export interface Empresa extends EmpresaResumo {
  socios: Socio[]
  vinculos: Vinculo[]
  indicadores: Indicador[]
  registros_juridicos: RegistroJuridico[]
}

export interface Consulta {
  id: number
  empresa_id: number
  empresa: string
  finalidade: string
  criada_em: string
}

export interface EmpresaParticipada extends EmpresaResumo {
  percentual: string
}

export interface SocioArvore extends Socio {
  outras_empresas: EmpresaParticipada[]
}

export interface ArvoreSocietaria {
  empresa: EmpresaResumo
  socios: SocioArvore[]
  origem: string
}

export type CenarioBureau = 'normal' | 'lento' | 'falha' | 'timeout'
export type ProvedorBureau = 'bureau-a' | 'bureau-b'
export type SituacaoIntegracao = 'sucesso' | 'timeout' | 'indisponivel' | 'erro_http' | 'resposta_invalida'

export interface ResultadoIntegracao {
  execucao_id: number
  provedor: ProvedorBureau
  cenario: CenarioBureau
  situacao: SituacaoIntegracao
  http_status: number | null
  duracao_ms: number
  erro: string | null
  score: number | null
  pendencias: number | null
  protestos: number | null
  atualizado_em: string | null
}

export interface ExecucaoIntegracao {
  id: number
  empresa_id: number
  criada_em: string
  resultados: ResultadoIntegracao[]
}

export interface Verificacao {
  consulta: { id: number; empresa_id: number; mensagem: string }
  fontes_atualizadas: number
  empresa: Empresa
}

export interface RelatorioConsolidado {
  referencia: string
  gerado_em: string
  dados_ficticios: true
  empresa: Empresa
  sumario: {
    fontes_financeiras: number
    socios: number
    vinculos: number
    registros_juridicos: number
    registros_em_andamento: number
    fontes_com_pendencias: number
    fontes_com_protestos: number
  }
  integracao_recente: ExecucaoIntegracao | null
  avisos: string[]
}

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { api } from './api'
import { textoExibicao } from './textoExibicao'
import ArvoreSocietaria from './components/ArvoreSocietaria.vue'
import RelatorioConsolidado from './components/RelatorioConsolidado.vue'
import type { Consulta, Empresa, EmpresaResumo, Verificacao } from './types'

type Aba = 'resumo' | 'arvore' | 'vinculos' | 'fontes' | 'relatorio' | 'historico'

const abas: { id: Aba; texto: string }[] = [
  { id: 'resumo', texto: 'Visão geral' },
  { id: 'arvore', texto: 'Árvore societária' },
  { id: 'vinculos', texto: 'Relacionamentos' },
  { id: 'fontes', texto: 'Fontes e registros' },
  { id: 'relatorio', texto: 'Relatório' },
  { id: 'historico', texto: 'Histórico' }
]

let sequenciaSelecao = 0
let sequenciaPesquisa = 0
let sequenciaHistorico = 0
let selecaoEmCurso: number | null = null
const verificacoesEmCurso = new Map<number, Promise<Verificacao>>()

function verificarUmaVez(id: number): Promise<Verificacao> {
  const existente = verificacoesEmCurso.get(id)
  if (existente) return existente
  const promessa = (async () => {
    try {
      return await api.verificar(id)
    } finally {
      // Mesmo uma seleção já substituída pode ter sido registrada no banco.
      await carregarHistorico()
      verificacoesEmCurso.delete(id)
    }
  })()
  verificacoesEmCurso.set(id, promessa)
  return promessa
}
const abaAtiva = ref<Aba>('resumo')

async function navegarAbas(evento: KeyboardEvent, atual: Aba) {
  const indice = abas.findIndex(aba => aba.id === atual)
  let proxima = indice
  if (evento.key === 'ArrowRight') proxima = (indice + 1) % abas.length
  else if (evento.key === 'ArrowLeft') proxima = (indice - 1 + abas.length) % abas.length
  else if (evento.key === 'Home') proxima = 0
  else if (evento.key === 'End') proxima = abas.length - 1
  else return
  evento.preventDefault()
  abaAtiva.value = abas[proxima].id
  await nextTick()
  document.getElementById(`aba-${abas[proxima].id}`)?.focus()
}

const busca = ref('')
const empresas = ref<EmpresaResumo[]>([])
const empresa = ref<Empresa | null>(null)
const consultas = ref<Consulta[]>([])
const carregando = ref(false)
const consultando = ref(false)
const verificando = ref(false)
const revisaoVerificacao = ref(0)
const erro = ref('')
const aviso = ref('')
const avisoHistorico = ref('')

const quantidadePendencias = computed(() => empresa.value?.indicadores.reduce((n, i) => n + i.pendencias, 0) ?? 0)
const quantidadeProtestos = computed(() => empresa.value?.indicadores.reduce((n, i) => n + i.protestos, 0) ?? 0)
const fontesDisponiveis = computed(() => empresa.value?.indicadores.length ?? 0)


function dataFormatada(data: string): string {
  const [ano, mes, dia] = data.substring(0, 10).split('-')
  return ano && mes && dia ? `${dia}/${mes}/${ano}` : data
}

async function pesquisar() {
  const atual = ++sequenciaPesquisa
  const selecaoInicial = sequenciaSelecao
  carregando.value = true
  erro.value = ''
  aviso.value = ''
  try {
    const resultado = await api.empresas(busca.value.trim())
    if (atual !== sequenciaPesquisa) return
    empresas.value = resultado
    if (selecaoInicial !== sequenciaSelecao) return
    if (!resultado.length) {
      ++sequenciaSelecao
      selecaoEmCurso = null
      consultando.value = false
      verificando.value = false
      empresa.value = null
      return
    }
    if (!empresa.value || !resultado.some(item => item.id === empresa.value?.id)) {
      await selecionar(resultado[0].id)
    }
  } catch (e) {
    if (atual === sequenciaPesquisa) erro.value = mensagemErro(e)
  } finally {
    if (atual === sequenciaPesquisa) carregando.value = false
  }
}

async function selecionar(id: number) {
  if (selecaoEmCurso === id) return
  selecaoEmCurso = id
  const atual = ++sequenciaSelecao
  consultando.value = true
  verificando.value = false
  erro.value = ''
  aviso.value = ''
  try {
    const dados = await api.empresa(id)
    if (atual !== sequenciaSelecao) return
    empresa.value = dados
    consultando.value = false
    verificando.value = true
    try {
      const verificacao = await verificarUmaVez(id)
      if (atual !== sequenciaSelecao) return
      empresa.value = verificacao.empresa
      revisaoVerificacao.value++
      if (verificacao.fontes_atualizadas < 2) {
        aviso.value = 'Nem todas as fontes puderam ser atualizadas. Confira a data dos indicadores.'
      }
    } catch (e) {
      if (atual === sequenciaSelecao) aviso.value = 'Dados exibidos. Não foi possível concluir a verificação; confira o histórico mais tarde.'
    } finally {
      if (atual === sequenciaSelecao) verificando.value = false
    }
  } catch (e) {
    if (atual === sequenciaSelecao) erro.value = mensagemErro(e)
  } finally {
    if (atual === sequenciaSelecao) {
      consultando.value = false
      selecaoEmCurso = null
    }
  }
}

async function carregarHistorico() {
  const atual = ++sequenciaHistorico
  try {
    const resultado = await api.consultas()
    if (atual === sequenciaHistorico) {
      consultas.value = resultado
      avisoHistorico.value = ''
    }
  } catch {
    if (atual === sequenciaHistorico) avisoHistorico.value = 'Não foi possível atualizar o histórico. Os registros carregados foram preservados.'
  }
}

function mensagemErro(e: unknown): string {
  return e instanceof Error ? e.message : 'Não foi possível concluir a operação.'
}

onMounted(async () => {
  await Promise.all([pesquisar(), carregarHistorico()])
})
</script>

<template>
  <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>
  <div class="shell">
    <aside class="sidebar" aria-label="Informações do projeto">
      <div class="brand">
        <span class="brand-symbol">N</span>
        <div>
          <strong>NEXUS<span class="brand-dot">.</span></strong>
          <small>Pesquisa e inteligência</small>
        </div>
      </div>

      <div class="side-section-title">NAVEGAÇÃO</div>
      <div class="side-active" aria-current="page">Pesquisa empresarial</div>
      <div class="side-section-title side-second">ANÁLISE</div>
      <div class="side-note">Perfil empresarial, vínculos societários, indicadores e histórico de verificações.</div>
      <div class="sidebar-bottom">
        <span class="live-indicator" aria-hidden="true"></span>
        <div>
          <strong>Ambiente local</strong>
          <small>Pesquisa empresarial</small>
        </div>
      </div>
    </aside>

    <main class="main" id="conteudo-principal" tabindex="-1">
      <header class="topbar">
        <div class="breadcrumbs" aria-label="Localização atual">Pesquisa empresarial</div>
        <div class="topbar-right">Ambiente local <span class="topbar-version">v0.6</span></div>
      </header>

      <div class="content">
        <div class="intro">
          <div>
            <div class="eyebrow">PESQUISA EMPRESARIAL</div>
            <h1>Análise empresarial</h1>
            <p>Consulte informações financeiras, registros e conexões societárias em um único lugar.</p>
          </div>
        </div>

        <form class="search-panel" @submit.prevent="pesquisar">
          <div class="search-field">
            <label for="pesquisa-empresa">Nome ou código da empresa</label>
            <input id="pesquisa-empresa" v-model="busca" type="search" maxlength="100" autocomplete="off" placeholder="Ex.: Aurora" />
          </div>
          <button class="primary-button" type="submit" :disabled="carregando">{{ carregando ? 'Pesquisando...' : 'Pesquisar' }}</button>
        </form>

        <div v-if="erro" class="alert error" role="alert">{{ erro }}</div>
        <p v-if="carregando || consultando" role="status" class="loading-note">{{ carregando ? 'Pesquisando empresas...' : 'Carregando dados da empresa...' }}</p>
        <p v-if="verificando" class="loading-note" role="status">Verificando informações e atualizando o histórico...</p>
        <div v-if="aviso" class="loading-note" role="status">{{ aviso }}</div>

        <p v-if="avisoHistorico" class="loading-note" role="status">{{ avisoHistorico }}</p>
        <p v-if="empresa" class="sr-only" role="status">Empresa selecionada: {{ empresa.razao_social }}</p>
        <div class="body-grid">
          <section class="directory-panel" aria-labelledby="titulo-entidades">
            <div class="panel-title-row"><h2 id="titulo-entidades">Entidades</h2><span class="count">{{ empresas.length }}</span></div>
            <div class="directory-subtitle">Resultados da pesquisa</div>
            <div v-if="!empresas.length" class="empty-left">Nenhuma empresa encontrada.</div>
            <button
              v-for="item in empresas"
              :key="item.id"
              type="button"
              class="company-option"
              :class="{ selected: empresa?.id === item.id }"
              :aria-pressed="empresa?.id === item.id"
              @click="selecionar(item.id)"
            >
              <span class="company-avatar" aria-hidden="true">{{ item.razao_social.substring(0, 1) }}</span>
              <span class="company-option-details">
                <strong>{{ item.razao_social }}</strong>
                <small>{{ item.codigo_demo }}</small>
              </span>
              <span class="company-chevron" aria-hidden="true">›</span>
            </button>
          </section>

          <section class="report-panel" v-if="empresa" aria-labelledby="titulo-empresa" :aria-busy="consultando">
            <div class="report-header">
              <div class="report-header-left">
                <div class="company-big-avatar" aria-hidden="true">{{ empresa.razao_social.substring(0, 1) }}</div>
                <div>
                  <div class="mini-label">PERFIL DE ENTIDADE</div>
                  <h2 id="titulo-empresa">{{ empresa.razao_social }}</h2>
                  <div class="company-sub">{{ empresa.codigo_demo }} <span>·</span> {{ empresa.cidade }}, {{ empresa.uf }}</div>
                </div>
              </div>
              <span class="status"><span></span>{{ empresa.situacao }}</span>
            </div>

            <div class="tabs" role="tablist" aria-label="Seções da análise" aria-orientation="horizontal">
              <button
                v-for="aba in abas" :id="`aba-${aba.id}`" :key="aba.id"
                role="tab" type="button"
                :aria-selected="abaAtiva === aba.id"
                :aria-controls="abaAtiva === aba.id ? 'painel-ativo' : undefined"
                :tabindex="abaAtiva === aba.id ? 0 : -1"
                :class="{ active: abaAtiva === aba.id }"
                @click="abaAtiva = aba.id"
                @keydown="navegarAbas($event, aba.id)"
              >{{ aba.texto }}</button>
            </div>

            <div class="tab-content" role="tabpanel" id="painel-ativo" :aria-labelledby="`aba-${abaAtiva}`" tabindex="0" v-if="abaAtiva === 'resumo'">
              <div class="section-label">PANORAMA DA EMPRESA</div>
              <div class="metrics">
                <div class="metric"><small>Fontes financeiras</small><strong>{{ fontesDisponiveis }}</strong><span>consultadas</span></div>
                <div class="metric"><small>Pendências reportadas</small><strong>{{ quantidadePendencias }}</strong><span>ocorrências por fonte*</span></div>
                <div class="metric"><small>Protestos reportados</small><strong>{{ quantidadeProtestos }}</strong><span>ocorrências por fonte*</span></div>
              </div>
              <div class="info-grid">
                <div class="info-card">
                  <div class="info-head"><h3>Quadro societário</h3><span>{{ empresa.socios.length }} sócios</span></div>
                  <div v-for="socio in empresa.socios" :key="socio.id" class="detail-line"><div class="person-dot" aria-hidden="true">{{ socio.nome.substring(0, 1) }}</div><span>{{ socio.nome }}</span><strong>{{ Number(socio.percentual).toFixed(0) }}%</strong></div>
                </div>
                <div class="info-card">
                  <div class="info-head"><h3>Relacionamentos</h3><span>{{ empresa.vinculos.length }} vínculos</span></div>
                  <div v-if="!empresa.vinculos.length" class="empty-text">Nenhuma conexão cadastrada.</div>
                  <button v-for="vinculo in empresa.vinculos" :key="vinculo.id" class="related-line" type="button" @click="selecionar(vinculo.empresa_relacionada_id)"><span class="connected-icon" aria-hidden="true">↗</span><span><strong>{{ vinculo.empresa_relacionada }}</strong><small>{{ vinculo.tipo }}</small></span><span class="company-chevron" aria-hidden="true">›</span></button>
                </div>
              </div>
              <div class="bottom-notice">* Indicadores de fontes diferentes podem se referir ao mesmo evento. Essas contagens podem se sobrepor e não representam necessariamente ocorrências únicas.</div>
            </div>

            <div class="tab-content" role="tabpanel" id="painel-ativo" :aria-labelledby="`aba-${abaAtiva}`" tabindex="0" v-else-if="abaAtiva === 'arvore'">
              <ArvoreSocietaria :empresa-id="empresa.id" @navegar="selecionar" />
            </div>

            <div class="tab-content" role="tabpanel" id="painel-ativo" :aria-labelledby="`aba-${abaAtiva}`" tabindex="0" v-else-if="abaAtiva === 'vinculos'">
              <div class="section-label">ESTRUTURA E CONEXÕES</div>
              <div class="section-heading"><h3>Participações societárias</h3><p>Selecione uma empresa relacionada para explorar sua estrutura.</p></div>
              <div class="relationship-list" v-for="socio in empresa.socios" :key="socio.id"><div class="person-dot large" aria-hidden="true">{{ socio.nome.substring(0, 1) }}</div><div class="relationship-details"><strong>{{ socio.nome }}</strong><small>Participação na empresa selecionada</small></div><span class="pill">{{ Number(socio.percentual).toFixed(0) }}%</span></div>
              <div class="section-heading spacious"><h3>Empresas relacionadas</h3></div>
              <div v-if="!empresa.vinculos.length" class="empty-text">Nenhum vínculo empresarial cadastrado.</div>
              <button v-for="vinculo in empresa.vinculos" :key="vinculo.id" class="relationship-list clickable" type="button" @click="selecionar(vinculo.empresa_relacionada_id)"><span class="connected-icon" aria-hidden="true">↗</span><div class="relationship-details"><strong>{{ vinculo.empresa_relacionada }}</strong><small>{{ vinculo.descricao }}</small></div><span class="arrow-next" aria-hidden="true">→</span></button>
              <div class="hint-box">Explore a árvore societária para acompanhar participações em outras empresas.</div>
            </div>

            <div class="tab-content" role="tabpanel" id="painel-ativo" :aria-labelledby="`aba-${abaAtiva}`" tabindex="0" v-else-if="abaAtiva === 'fontes'">
              <div class="section-label">RASTREABILIDADE</div>
              <div class="section-heading"><h3>Indicadores financeiros</h3><p>Os scores de fontes diferentes não são somados nem comparados diretamente.</p></div>
              <div class="source-row" v-for="item in empresa.indicadores" :key="item.id">
                <div><span class="source-label">FONTE</span><h4>{{ textoExibicao(item.fonte) }}</h4><small>Atualizado em {{ dataFormatada(item.atualizado_em) }}</small></div>
                <div class="score-group"><strong>{{ item.score ?? '—' }}</strong><span>pontuação</span></div>
                <div class="source-counters"><span>{{ item.pendencias }} pendência(s)</span><span>{{ item.protestos }} protesto(s)</span></div>
              </div>
              <div class="section-heading spacious"><h3>Registros jurídicos</h3></div>
              <div v-if="!empresa.registros_juridicos.length" class="empty-text">Nenhum registro disponível.</div>
              <div class="legal-record" v-for="registro in empresa.registros_juridicos" :key="registro.id"><div><strong>{{ registro.tipo }}</strong><span class="legal-state">{{ registro.situacao }}</span></div><p>{{ textoExibicao(registro.resumo) }}</p><small>{{ textoExibicao(registro.fonte) }} · {{ dataFormatada(registro.data_registro) }}</small></div>
            </div>

            <div class="tab-content" role="tabpanel" id="painel-ativo" :aria-labelledby="`aba-${abaAtiva}`" tabindex="0" v-else-if="abaAtiva === 'relatorio'">
              <RelatorioConsolidado :key="`${empresa.id}-${revisaoVerificacao}`" :empresa-id="empresa.id" />
            </div>

            <div class="tab-content" role="tabpanel" id="painel-ativo" :aria-labelledby="`aba-${abaAtiva}`" tabindex="0" v-else>
              <div class="section-label">REGISTROS DE CONSULTA</div>
              <div class="section-heading"><h3>Histórico de verificações</h3><p>O histórico é atualizado automaticamente ao selecionar uma empresa.</p></div>
              <div v-if="!consultas.length" class="empty-text">Nenhuma verificação registrada até o momento.</div>
              <div v-for="consulta in consultas" :key="consulta.id" class="history-row"><div class="history-id">#{{ String(consulta.id).padStart(3, '0') }}</div><div><strong>{{ consulta.empresa }}</strong><small>{{ consulta.finalidade.includes('Demonstração') ? 'Verificação de perfil empresarial' : consulta.finalidade }}</small></div><time :datetime="consulta.criada_em.replace(' ', 'T')">{{ consulta.criada_em }}</time></div>
            </div>
          </section>

          <section v-else class="report-panel empty-report">

            <h2>{{ consultando ? 'Carregando empresa...' : 'Nenhuma empresa selecionada' }}</h2>
            <p>Pesquise uma empresa para começar.</p>
          </section>
        </div>

        <footer class="footer"><span>NEXUS © 2026 · Projeto de estudo independente</span><span>Todos os nomes, empresas, pontuações, fontes e registros são fictícios. Nenhuma consulta real é realizada.</span></footer>
      </div>
    </main>
  </div>
</template>

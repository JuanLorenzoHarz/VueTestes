<script setup lang="ts">
import { ref, watch } from 'vue'
import { api } from '../api'
import { textoExibicao } from '../textoExibicao'
import type { RelatorioConsolidado } from '../types'

const props = defineProps<{ empresaId: number }>()
const relatorio = ref<RelatorioConsolidado | null>(null)
const carregando = ref(false)
const erro = ref('')
let sequencia = 0

function dataCurta(value: string | null): string {
  if (!value) return 'Não informada'
  const [ano, mes, dia] = value.slice(0, 10).split('-')
  return ano && mes && dia ? `${dia}/${mes}/${ano}` : value
}

function dataHora(value: string): string {
  const date = new Date(value.endsWith('Z') ? value : value.replace(' ', 'T') + 'Z')
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Sao_Paulo' })
}


function numero(value: number | string): string {
  return Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 })
}

function estadoIntegracao(status: string): string {
  const estados: Record<string, string> = {
    sucesso: 'Sucesso', timeout: 'Tempo esgotado', indisponivel: 'Indisponível',
    erro_http: 'Falha na atualização', resposta_invalida: 'Resposta inválida'
  }
  return estados[status] || status
}

async function carregar() {
  const id = props.empresaId
  const atual = ++sequencia
  carregando.value = true
  erro.value = ''
  relatorio.value = null
  try {
    const resposta = await api.relatorio(id)
    if (sequencia === atual) relatorio.value = resposta
  } catch (e) {
    if (sequencia === atual) erro.value = e instanceof Error ? e.message : 'Não foi possível gerar o relatório.'
  } finally {
    if (sequencia === atual) carregando.value = false
  }
}

function imprimir() {
  if (relatorio.value) window.print()
}

watch(() => props.empresaId, () => { void carregar() }, { immediate: true })
</script>

<template>
  <section class="consolidated-report">
    <div class="report-toolbar">
      <div>
        <div class="section-label">RELATÓRIO CONSOLIDADO · NEXUS</div>
        <h3>Visão unificada das fontes</h3>
        <p>Informações financeiras, societárias e jurídicas reunidas em um único relatório.</p>
      </div>
      <div class="report-toolbar-actions">
        <button type="button" class="report-secondary-button" :disabled="carregando" @click="carregar">Atualizar</button>
        <button type="button" class="primary-button" :disabled="!relatorio || carregando" @click="imprimir">Imprimir / salvar PDF ↗</button>
      </div>
    </div>

    <div v-if="erro" class="alert error" role="alert">{{ erro }}</div>
    <div v-if="carregando" class="empty-text" role="status">Montando relatório...</div>

    <article v-if="relatorio" class="report-print" aria-label="Relatório consolidado para impressão">
      <header class="print-document-header">
        <div>
          <div class="print-brand">NEXUS<span>.</span> <small>PESQUISA EMPRESARIAL</small></div>
          <h2>Relatório consolidado</h2>
          <p>Análise empresarial e rastreabilidade de informações</p>
        </div>
      </header>

      <div class="report-metadata">
        <span><b>Referência</b>{{ relatorio.referencia }}</span>
        <span><b>Gerado em</b>{{ dataHora(relatorio.gerado_em) }} (Brasília)</span>
      </div>

      <section class="print-section">
        <div class="print-section-heading"><span>01</span><h3>Identificação da entidade</h3></div>
        <h4 class="report-company-name">{{ relatorio.empresa.razao_social }}</h4>
        <div class="report-facts">
          <span><b>Código de referência</b>{{ relatorio.empresa.codigo_demo }}</span>
          <span><b>Situação informada</b>{{ relatorio.empresa.situacao }}</span>
          <span><b>Setor</b>{{ relatorio.empresa.setor }}</span>
          <span><b>Localidade</b>{{ relatorio.empresa.cidade }}/{{ relatorio.empresa.uf }}</span>
        </div>
      </section>

      <section class="print-section">
        <div class="print-section-heading"><span>02</span><h3>Panorama descritivo</h3></div>
        <div class="report-kpi-grid">
          <div><strong>{{ relatorio.sumario.fontes_financeiras }}</strong><span>Fontes financeiras</span></div>
          <div><strong>{{ relatorio.sumario.socios }}</strong><span>Sócios</span></div>
          <div><strong>{{ relatorio.sumario.vinculos }}</strong><span>Vínculos empresariais</span></div>
          <div><strong>{{ relatorio.sumario.registros_juridicos }}</strong><span>Registros jurídicos</span></div>
        </div>
        <p class="report-context">{{ relatorio.sumario.fontes_com_pendencias }} fonte(s) reportam pendências; {{ relatorio.sumario.fontes_com_protestos }} fonte(s) reportam protestos; {{ relatorio.sumario.registros_em_andamento }} registro(s) jurídico(s) são apresentados como “Em andamento”. Esses indicadores são contagens por fonte, não uma classificação de risco.</p>
      </section>

      <section class="print-section">
        <div class="print-section-heading"><span>03</span><h3>Indicadores financeiros por fonte</h3></div>
        <div class="report-table-scroll">
          <table class="report-data-table">
            <caption class="sr-only">Indicadores financeiros por fonte</caption>
            <thead><tr><th scope="col">Fonte</th><th scope="col">Score*</th><th scope="col">Pendências</th><th scope="col">Protestos</th><th scope="col">Referência</th></tr></thead>
            <tbody>
              <tr v-for="item in relatorio.empresa.indicadores" :key="item.id">
                <td>{{ textoExibicao(item.fonte) }}</td><td>{{ item.score ?? 'N/D' }}</td><td>{{ item.pendencias }}</td><td>{{ item.protestos }}</td><td>{{ dataCurta(item.atualizado_em) }}</td>
              </tr>
              <tr v-if="!relatorio.empresa.indicadores.length"><td colspan="5">Nenhuma fonte financeira.</td></tr>
            </tbody>
          </table>
        </div>
        <p class="report-footnote">* Pontuações específicas de cada fonte. O Nexus não calcula score único ou média entre fontes.</p>
      </section>

      <section class="print-section">
        <div class="print-section-heading"><span>04</span><h3>Participações e relacionamentos</h3></div>
        <div class="report-split">
          <div>
            <h4>Sócios e participações</h4>
            <ul class="report-lines">
              <li v-for="socio in relatorio.empresa.socios" :key="socio.id"><span>{{ socio.nome }}</span><strong>{{ numero(socio.percentual) }}%</strong></li>
              <li v-if="!relatorio.empresa.socios.length">Sem sócios.</li>
            </ul>
          </div>
          <div>
            <h4>Empresas relacionadas</h4>
            <ul class="report-lines">
              <li v-for="vinculo in relatorio.empresa.vinculos" :key="vinculo.id"><span>{{ vinculo.empresa_relacionada }}<small>{{ vinculo.tipo }} — {{ vinculo.descricao }}</small></span></li>
              <li v-if="!relatorio.empresa.vinculos.length">Sem vínculos.</li>
            </ul>
          </div>
        </div>
      </section>

      <section class="print-section">
        <div class="print-section-heading"><span>05</span><h3>Registros jurídicos</h3></div>
        <div v-for="registro in relatorio.empresa.registros_juridicos" :key="registro.id" class="report-legal-item">
          <div><strong>{{ registro.tipo }}</strong><span>{{ registro.situacao }}</span></div>
          <p>{{ textoExibicao(registro.resumo) }}</p>
          <small>Origem: {{ textoExibicao(registro.fonte) }} · Data: {{ dataCurta(registro.data_registro) }}</small>
        </div>
        <p v-if="!relatorio.empresa.registros_juridicos.length" class="report-context">Nenhum registro jurídico disponível.</p>
      </section>

      <section class="print-section">
        <div class="print-section-heading"><span>06</span><h3>Situação da última verificação</h3></div>
        <template v-if="relatorio.integracao_recente">
          <p class="report-context">Verificação em {{ dataHora(relatorio.integracao_recente.criada_em) }} (Brasília)</p>
          <div class="report-table-scroll">
            <table class="report-data-table">
              <caption class="sr-only">Situação das fontes na última verificação</caption>
              <thead><tr><th scope="col">Fonte</th><th scope="col">Situação</th><th scope="col">Observação</th></tr></thead>
              <tbody>
                <tr v-for="item in relatorio.integracao_recente.resultados" :key="item.provedor">
                  <td>{{ item.provedor === 'bureau-a' ? 'Bureau A' : 'Bureau B' }}</td><td>{{ estadoIntegracao(item.situacao) }}</td><td>{{ item.situacao === 'sucesso' ? 'Dados atualizados' : 'Dados anteriores preservados, quando disponíveis' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
        <p v-else class="report-context">Não há verificações recentes para esta empresa.</p>
      </section>

      <section class="print-section report-notices">
        <div class="print-section-heading"><span>07</span><h3>Metodologia e ressalvas</h3></div>
        <ul><li v-for="(aviso, indice) in relatorio.avisos" :key="indice">{{ aviso }}</li></ul>
      </section>
      <footer class="print-document-footer">NEXUS · Projeto independente · Dados fictícios, sem consultas reais · {{ relatorio.referencia }}</footer>
    </article>
  </section>
</template>

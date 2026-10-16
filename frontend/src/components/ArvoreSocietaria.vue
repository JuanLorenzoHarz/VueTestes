<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { api } from '../api'
import type { ArvoreSocietaria, EmpresaParticipada, SocioArvore } from '../types'

const props = defineProps<{ empresaId: number }>()
const emit = defineEmits<{ (event: 'navegar', id: number): void }>()

type Foco =
  | { tipo: 'socio'; socio: SocioArvore }
  | { tipo: 'empresa'; empresa: EmpresaParticipada; socio: SocioArvore }
  | null

const arvore = ref<ArvoreSocietaria | null>(null)
const carregando = ref(false)
const erro = ref('')
const foco = ref<Foco>(null)
const expandidos = ref<number[]>([])
const totalEmpresasRelacionadas = computed(() => new Set(
  arvore.value?.socios.flatMap(socio => socio.outras_empresas.map(empresa => empresa.id)) ?? []
).size)
let versaoRequisicao = 0

function porcentagem(valor: string): string {
  return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(Number(valor)) + '%'
}

function fecharDetalhes() {
  const botaoSelecionado = document.querySelector<HTMLElement>('.societary-tree .node-highlight')
  foco.value = null
  void nextTick().then(() => botaoSelecionado?.focus())
}

function alternarSocio(id: number) {
  expandidos.value = expandidos.value.includes(id)
    ? expandidos.value.filter(item => item !== id)
    : [...expandidos.value, id]
}

async function carregarArvore(id: number) {
  const requisicao = ++versaoRequisicao
  carregando.value = true
  arvore.value = null
  foco.value = null
  erro.value = ''
  try {
    const resultado = await api.arvore(id)
    if (requisicao !== versaoRequisicao) return
    arvore.value = resultado
    expandidos.value = resultado.socios.map(socio => socio.id)
  } catch (e) {
    if (requisicao === versaoRequisicao) {
      erro.value = e instanceof Error ? e.message : 'Não foi possível carregar a árvore.'
    }
  } finally {
    if (requisicao === versaoRequisicao) carregando.value = false
  }
}

watch(() => props.empresaId, carregarArvore, { immediate: true })
</script>

<template>
  <section class="societary-tree" aria-label="Árvore societária">
    <div class="tree-heading">
      <div>
        <div class="section-label">ESTRUTURA SOCIETÁRIA</div>
        <h3>Árvore de participações</h3>
        <p>Selecione um sócio ou empresa para explorar os vínculos registrados.</p>
      </div>
      <div class="tree-legend" aria-label="Legenda">
        <span><i class="legend-marker legend-business"></i> Empresa</span>
        <span><i class="legend-marker legend-person"></i> Pessoa</span>
      </div>
    </div>

    <div v-if="carregando" class="tree-message" role="status">Carregando estrutura societária...</div>
    <div v-else-if="erro" class="tree-message tree-error" role="alert">
      {{ erro }}
      <button type="button" @click="carregarArvore(empresaId)">Tentar novamente</button>
    </div>
    <template v-else-if="arvore">
      <div class="tree-summary">
        <span><strong>{{ arvore.socios.length }}</strong> sócio(s) direto(s)</span>
        <span><strong>{{ totalEmpresasRelacionadas }}</strong> outra(s) empresa(s)</span>
        <span>Quadro societário</span>
      </div>

      <div class="tree-scroll" role="region" tabindex="0" aria-label="Árvore de participações; use as setas para rolar horizontalmente quando necessário">
        <div class="tree-canvas" :style="{ '--branch-count': String(Math.max(1, arvore.socios.length)), '--edge-pct': `${50 / Math.max(1, arvore.socios.length)}%` }">
          <div class="tree-main-node">
            <div class="tree-node-icon tree-icon-business" aria-hidden="true">▦</div>
            <div class="tree-node-overline">EMPRESA PESQUISADA</div>
            <strong>{{ arvore.empresa.razao_social }}</strong>
            <small>{{ arvore.empresa.codigo_demo }} · {{ arvore.empresa.cidade }}/{{ arvore.empresa.uf }}</small>
          </div>
          <div v-if="arvore.socios.length" class="tree-stem"></div>
          <div v-if="arvore.socios.length" class="tree-branches" :class="{ 'single-branch': arvore.socios.length === 1 }">
            <div v-for="socio in arvore.socios" :key="socio.id" class="tree-branch">
              <button
                class="tree-person-node"
                type="button"
                :aria-pressed="foco?.tipo === 'socio' && foco.socio.id === socio.id"
                :class="{ 'node-highlight': foco?.tipo === 'socio' && foco.socio.id === socio.id }"
                @click="foco = { tipo: 'socio', socio }"
              >
                <span class="tree-person-symbol" aria-hidden="true">{{ socio.nome.slice(0, 1) }}</span>
                <span class="tree-node-overline">SÓCIO DIRETO</span>
                <strong>{{ socio.nome }}</strong>
                <span class="tree-percent">{{ porcentagem(socio.percentual) }} da empresa consultada</span>
              </button>

              <template v-if="socio.outras_empresas.length">
                <button
                  type="button"
                  class="tree-expand"
                  :aria-expanded="expandidos.includes(socio.id)"
                  @click="alternarSocio(socio.id)"
                >
                  <span>{{ expandidos.includes(socio.id) ? '−' : '+' }}</span>
                  {{ socio.outras_empresas.length }} outra(s) participação(ões)
                </button>
                <div v-if="expandidos.includes(socio.id)" class="tree-holdings">
                  <button
                    v-for="companhia in socio.outras_empresas"
                    :key="companhia.id"
                    type="button"
                    class="tree-holding-node"
                    :aria-pressed="foco?.tipo === 'empresa' && foco.empresa.id === companhia.id && foco.socio.id === socio.id"
                    :class="{ 'node-highlight': foco?.tipo === 'empresa' && foco.empresa.id === companhia.id && foco.socio.id === socio.id }"
                    @click="foco = { tipo: 'empresa', empresa: companhia, socio }"
                  >
                    <span class="tree-holding-icon" aria-hidden="true">▦</span>
                    <span class="tree-holding-details">
                      <span class="tree-node-overline">TAMBÉM PARTICIPA DE</span>
                      <strong>{{ companhia.razao_social }}</strong>
                      <small>{{ porcentagem(companhia.percentual) }} de participação</small>
                    </span>
                    <span class="tree-holding-arrow" aria-hidden="true">↗</span>
                  </button>
                </div>
              </template>
              <div v-else class="tree-no-holdings">Sem outras participações</div>
            </div>
          </div>
          <div v-else class="tree-message">Nenhum sócio cadastrado.</div>
        </div>
      </div>

      <div v-if="foco?.tipo === 'socio'" class="tree-detail" aria-live="polite">
        <div class="tree-detail-avatar">{{ foco.socio.nome.slice(0, 1) }}</div>
        <div class="tree-detail-body">
          <div class="tree-node-overline">DETALHES DO SÓCIO</div>
          <h4>{{ foco.socio.nome }}</h4>
          <p>Participação na empresa pesquisada: <strong>{{ porcentagem(foco.socio.percentual) }}</strong></p>
          <p>Outras empresas: <strong>{{ foco.socio.outras_empresas.length }}</strong></p>
        </div>
        <button type="button" class="tree-close" aria-label="Fechar detalhes da seleção" @click="fecharDetalhes">×</button>
      </div>
      <div v-else-if="foco?.tipo === 'empresa'" class="tree-detail" aria-live="polite">
        <div class="tree-detail-avatar tree-detail-company">▦</div>
        <div class="tree-detail-body">
          <div class="tree-node-overline">EMPRESA RELACIONADA POR PARTICIPAÇÃO</div>
          <h4>{{ foco.empresa.razao_social }}</h4>
          <p>{{ foco.socio.nome }} detém <strong>{{ porcentagem(foco.empresa.percentual) }}</strong> desta empresa.</p>
          <p>{{ foco.empresa.codigo_demo }} · {{ foco.empresa.cidade }}/{{ foco.empresa.uf }}</p>
          <button type="button" class="tree-navigation" @click="emit('navegar', foco.empresa.id)">
            Abrir análise desta empresa <span>→</span>
          </button>
        </div>
        <button type="button" class="tree-close" aria-label="Fechar detalhes da seleção" @click="fecharDetalhes">×</button>
      </div>
      <div v-else class="tree-detail-hint">Use Tab para percorrer os nós e Enter ou Espaço para consultar os detalhes. Expanda as participações com os botões correspondentes.</div>

      <p class="tree-disclaimer">
        As ligações representam participações de uma mesma pessoa em empresas diferentes;
        não indicam controle entre empresas, subsidiárias ou irregularidades.
      </p>
    </template>
  </section>
</template>

<style scoped>
.societary-tree { width: 100%; min-width: 0; color: var(--text); }
.tree-heading { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
.tree-heading h3 { margin: 7px 0 5px; font-size: 20px; }
.tree-heading p { color: var(--secondary); font-size: 14px; margin: 0; }
.tree-legend { display: flex; gap: 16px; color: var(--secondary); font-size: 13px; }
.tree-legend span { display: flex; align-items: center; gap: 7px; }
.legend-marker { display: block; width: 10px; height: 10px; border-radius: 2px; background: #406d8d; }
.legend-person { background: #617281; border-radius: 50%; }
.tree-summary { display: flex; flex-wrap: wrap; gap: 9px 18px; padding: 11px 0; margin-bottom: 12px; color: var(--secondary); font-size: 14px; }
.tree-summary strong { color: var(--text); }
.tree-scroll { max-width: 100%; overflow: auto; padding: 24px 14px; background: var(--canvas); border-radius: 5px; scrollbar-color: #8395a5 #e7edf2; }
.tree-canvas { min-width: 100%; width: max-content; margin: 0 auto; }
.tree-main-node { width: 280px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; align-items: center; gap: 5px; text-align: center; background: #dae8f0; border: 1px solid #6d90a7; border-radius: 5px; }
.tree-main-node strong { font-size: 15px; line-height: 1.4; }
.tree-main-node small { font-size: 13px; color: var(--secondary); }
.tree-node-icon { display: none; }
.tree-node-overline { color: var(--secondary); font-size: 12px; font-weight: 700; }
.tree-stem { height: 30px; width: 1px; margin: auto; background: #94a6b5; }
.tree-branches { display: grid; grid-template-columns: repeat(var(--branch-count), minmax(210px, 1fr)); gap: 15px; position: relative; }
.tree-branches::before { content: ''; position: absolute; top: 0; left: var(--edge-pct); right: var(--edge-pct); height: 1px; background: #94a6b5; }
.tree-branches.single-branch::before { display: none; }
.tree-branch { min-width: 0; padding: 27px 3px 0; position: relative; display: flex; flex-direction: column; }
.tree-branch::before { content: ''; position: absolute; top: 0; left: 50%; width: 1px; height: 27px; background: #94a6b5; }
.tree-person-node { display: flex; width: 100%; min-height: 155px; padding: 15px 12px; gap: 6px; align-items: center; flex-direction: column; text-align: center; color: var(--text); background: var(--surface); border: 1px solid #9aabb9; border-radius: 5px; }
.tree-person-node:hover, .tree-person-node.node-highlight { background: #e8f1f7; border-color: #14547a; }
.tree-person-node strong { font-size: 15px; overflow-wrap: anywhere; }
.tree-person-symbol { flex: none; aspect-ratio: 1; display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; color: #254359; background: #e0e7ed; font-weight: 700; }
.tree-percent { font-size: 13px; color: var(--secondary); }
.tree-expand { background: none; border: 0; color: var(--accent); padding: 13px 4px; min-height: 44px; font-size: 13px; font-weight: 650; }
.tree-expand:hover { text-decoration: underline; }
.tree-expand span { font-size: 17px; margin-right: 5px; }
.tree-holdings { display: flex; flex-direction: column; gap: 10px; padding-top: 19px; position: relative; }
.tree-holdings::before { content: ''; position: absolute; top: 0; left: 50%; height: 19px; width: 1px; background: #94a6b5; }
.tree-holding-node { width: 100%; display: flex; align-items: center; gap: 9px; min-height: 78px; padding: 12px; text-align: left; color: var(--text); background: var(--surface); border: 1px solid #b6c3cd; border-radius: 5px; }
.tree-holding-node:hover, .tree-holding-node.node-highlight { background: #e8f1f7; border-color: #14547a; }
.tree-holding-icon { color: var(--accent); }
.tree-holding-details { min-width: 0; display: flex; flex: 1; flex-direction: column; gap: 4px; }
.tree-holding-details strong { font-size: 14px; overflow-wrap: anywhere; }
.tree-holding-details small { font-size: 13px; color: var(--secondary); }
.tree-holding-arrow { color: var(--secondary); }
.tree-no-holdings { font-size: 13px; color: var(--secondary); padding: 15px 0; text-align: center; }
.tree-detail, .tree-detail-hint { margin-top: 18px; padding: 18px; background: #f1f5f8; border-radius: 5px; }
.tree-detail { display: flex; align-items: flex-start; gap: 14px; }
.tree-detail-avatar { aspect-ratio: 1; flex: none; width: 35px; height: 35px; display: grid; place-items: center; background: #dde6ed; color: #274a61; border-radius: 50%; font-weight: 700; }
.tree-detail-company { border-radius: 5px; }
.tree-detail-body { min-width: 0; flex: 1; }
.tree-detail-body h4 { margin: 4px 0 8px; font-size: 16px; }
.tree-detail-body p { font-size: 14px; color: var(--secondary); margin: 5px 0; }
.tree-detail-body p strong { color: var(--text); }
.tree-close { border: 0; background: none; font-size: 22px; color: var(--secondary); min-width: 40px; min-height: 40px; }
.tree-navigation { margin-top: 11px; background: var(--surface); color: var(--accent); border: 1px solid #7993a5; border-radius: 5px; padding: 9px 14px; font-size: 14px; font-weight: 650; min-height: 44px; }
.tree-navigation:hover { background: #e5eef5; }
.tree-detail-hint, .tree-disclaimer, .tree-message { font-size: 14px; color: var(--secondary); line-height: 1.6; }
.tree-disclaimer { margin: 19px 0 0; }
.tree-message { padding: 22px 0; }
.tree-error { color: #842b2b; }
.tree-error button { border: 0; background: transparent; color: var(--accent); text-decoration: underline; font-size: 14px; }
@media (max-width: 600px) { .tree-scroll { padding: 16px 10px; } }
</style>

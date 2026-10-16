"""Regressões básicas do escopo visual. Não substitui os testes do navegador."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
app = (root / 'frontend/src/App.vue').read_text(encoding='utf-8')
report = (root / 'frontend/src/components/RelatorioConsolidado.vue').read_text(encoding='utf-8')
backend = (root / 'api/public/index.php').read_text(encoding='utf-8')
client = (root / 'frontend/src/api.ts').read_text(encoding='utf-8')

def template(vue):
    return vue.split('<template>', 1)[1].rsplit('</template>', 1)[0]

screen_markup = re.sub(r'<footer\b[^>]*>.*?</footer>', '', template(app), flags=re.S)
report_markup = re.sub(r'<footer\b[^>]*>.*?</footer>', '', template(report), flags=re.S)
check = {
    'histórico registrado automaticamente': 'await api.verificar(id)' in app and 'await carregarHistorico()' in app,
    'verificação no backend': 'verificacoes$#' in backend,
    'requisição POST': "method: 'POST'" in client and '/verificacoes' in client,
    'sem botão manual de histórico': 'Registrar consulta a esta empresa' not in screen_markup,
    'sem aba técnica': "id: 'integracoes'" not in app and 'Integrações REST' not in screen_markup,
    'sem menções repetidas': not re.search(r'fict[ií]ci|simulad|hipot[eé]tic', screen_markup + report_markup, flags=re.I),
    'aviso acessível no rodapé da aplicação': 'fictícios' in template(app).split('<footer class="footer">')[-1],
    'aviso no rodapé do relatório': 'fictícios' in template(report).split('<footer class="print-document-footer">')[-1],
    'sem detalhes HTTP visíveis': '<th scope="col">HTTP' not in report_markup and ' ms</td>' not in report_markup,
}
for name, ok in check.items():
    print(('OK' if ok else 'FALHOU') + ': ' + name)
if not all(check.values()):
    raise SystemExit(1)

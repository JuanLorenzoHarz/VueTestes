"""Verificações estáticas limitadas: não substituem auditoria de acessibilidade."""
from pathlib import Path

root = Path(__file__).resolve().parents[1]
app = (root / 'frontend/src/App.vue').read_text(encoding='utf-8')
tree = (root / 'frontend/src/components/ArvoreSocietaria.vue').read_text(encoding='utf-8')
css = (root / 'frontend/src/style.css').read_text(encoding='utf-8')
readme = (root / 'README.md').read_text(encoding='utf-8')

checks = {
    'link de salto': 'class="skip-link" href="#conteudo-principal"' in app,
    'pesquisa com rótulo': 'for="pesquisa-empresa"' in app and 'id="pesquisa-empresa"' in app,
    'abas com foco controlado': 'role="tablist"' in app and 'aria-selected' in app and 'tabindex' in app,
    'teclas nas abas': all(k in app for k in ['ArrowLeft', 'ArrowRight', 'Home', 'End']),
    'foco visível': ':focus-visible' in css and 'outline: 3px' in css,
    'vínculos expansíveis': ':aria-expanded=' in tree,
    'retorno do foco na árvore': 'function fecharDetalhes()' in tree and 'botaoSelecionado?.focus()' in tree,
    'sem efeitos de glow': 'box-shadow:' not in css and 'radial-gradient' not in css and 'linear-gradient' not in css,
    'respeito à preferência de movimento': 'prefers-reduced-motion' in css,
    'motivação explicitada no README': 'uma escolha pessoal minha, baseada em acessibilidade' in readme,
}

for label, ok in checks.items():
    print(('OK' if ok else 'FALHOU') + ': ' + label)

if not all(checks.values()):
    raise SystemExit(1)

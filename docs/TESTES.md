# Parte 4B — Estratégia de testes

Os testes rodam **somente com dados de demonstração**. Não coloque dados pessoais reais nesta instalação.

## 1. Unidade: PHP (contratos e composição de relatório)

```bash
docker compose --profile tests run --rm tests-php
```

Equivalente, se PHP estiver instalado na máquina:

```bash
php tests/test_bureau_contract.php
php tests/test_report_service.php
```

**Cobertura funcional:** cenários obrigatórios, nomes de provedores, rejeição de valores inválidos, limites de score, datas impossíveis, dados não fictícios, contagens por fonte, ausência de empresa e falhas no relatório. Esses testes não exigem MySQL.

## 2. Contrato REST + MySQL (ambiente integrado)

```bash
docker compose up -d --build
docker compose --profile tests run --rm tests-rest
```

Alternativa: `python -m unittest discover -s tests -p test_api_rest.py -v`, com a API publicada em `http://127.0.0.1:8080`; use `NEXUS_API_URL` para modificar o endereço.

**Cobertura funcional:** pesquisa, perfil, árvore, relatório, status 404 e 422, registro automático de histórico por seleção, execução normal, falha 503, timeout, persistência e preservação do snapshot quando os dois provedores falham. Os testes modificam **apenas o banco local fictício**: criam consultas e execuções de integração.

Os testes REST são intencionalmente reais; não substituem o banco por mocks. É necessário iniciar MySQL, PHP e bureaus pelo Compose.

## 3. Vue: testes de componentes e cliente REST

```bash
docker compose up -d --build
docker compose exec frontend npm test
docker compose exec frontend npm run build
```

Ou dentro da pasta `frontend`, com Node.js 22+: `npm install`, `npm test`, `npm run build`.

**Cobertura funcional:** encode de pesquisas, POST automático de verificação, mensagens HTTP de erro, renderização e navegação pela árvore, expansão de sócios, loading/falha do relatório, visualização do relatório e atualização automática de histórico após seleção. A API de bureaus permanece coberta pelos testes backend. Usa Vitest, Vue Test Utils e jsdom; `fetch`/cliente REST são substituídos apenas nesses testes unitários de interface.

## 4. Simulação HTTP isolada dos bureaus

Exige Python 3 e PHP CLI na máquina, não usa banco:

```bash
python tests/test_mock_bureaus.py
```

Verifica contrato JSON, latência, erro 503 e timeout.

## Validação desta revisão — 16/10/2026

Executados em Docker e aprovados:

- PHP: `test_bureau_contract.php` e `test_report_service.php`.
- HTTP isolado: `test_mock_bureaus.py`, em contêiner temporário com PHP e Python.
- REST + MySQL: 6 testes de `test_api_rest.py`, incluindo persistência, data/hora, exatamente um registro por POST, GET sem gravação e acentos dos dados iniciais.
- Frontend: 20 testes Vitest em 5 arquivos; TypeScript e build Vite aprovados.
- Python: `test_accessibility_static.py` e `test_interface_scope.py`.

Chrome: inspeção em 1366×900 e 390×844, participantes com avatares de 38×38 px, ausência de overflow horizontal da página mobile, foco visível e histórico. A inspeção não equivale a auditoria completa com leitor de tela nem validação da paginação de um PDF exportado.

A suíte Vue cobre cliques concorrentes, retorno à empresa com verificação pendente, resposta de perfil atrasada, histórico inicial atrasado e preservação dos registros após falhas. A proteção contra duplicação é da instância da interface; clientes externos do endpoint POST não têm chave de idempotência.

`npm audit --omit=dev` não identificou vulnerabilidades. O audit completo identificou 3 entradas nas dependências de desenvolvimento (Vitest, @vitest/mocker e tinypool; 2 críticas e 1 moderada). A atualização sugerida exige mudança de versão principal e ficou fora desta revisão de interface.

## Próximos cenários úteis

Paginação do histórico, persistência após reinício, auditoria com leitor de tela e uma suíte browser E2E automatizada são incrementos futuros.

## Parte 5 — acessibilidade e navegação

O teste `frontend/src/App.a11y.test.ts` verifica rótulo da pesquisa, link para o conteúdo principal, estado de seleção de entidades, tablist, navegação por setas/Home/End e foco na aba ativa. Os testes da árvore também verificam o retorno de foco ao fechar detalhes.

```bash
docker compose exec frontend npm test
```

Além disso, um verificador estático sem dependências externas sinaliza regressões óbvias (a ausência de marcadores essenciais e a presença de efeitos decorativos proibidos):

```bash
python3 tests/test_accessibility_static.py
```

**Importante:** testes estáticos e de componentes não substituem testes manuais com teclado, zoom e leitor de tela. Consulte [ACESSIBILIDADE.md](ACESSIBILIDADE.md).


## Parte 6 — teste de fluxo automático

- `test_api_rest.py` testa `POST /api/empresas/1/verificacoes`, criação de registro e atualização das fontes.
- `App.a11y.test.ts` verifica que selecionar uma empresa inicia verificação sem botão separado, além de conferir a presença do aviso no rodapé.
- `api.test.ts` verifica a rota e o método POST, sem opções de simulação expostas no corpo.
- A aba de integrações foi removida; os testes do antigo componente foram removidos com ela, sem eliminar a cobertura dos bureaus no PHP/Python.

### Verificações de interface (sem Vue instalado)

```bash
python3 tests/test_interface_scope.py
```

Detecta regressões de escopo: presença indevida da aba de laboratório, botão manual de registro, avisos repetidos fora dos rodapés e ausência do fluxo automático. Não substitui a execução real no navegador.

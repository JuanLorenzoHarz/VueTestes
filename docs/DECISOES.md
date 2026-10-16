# Decisões de arquitetura — Parte 1

1. **Dados fictícios:** permitem demonstrar o fluxo sem contrato com provedores ou exposição de terceiros.
2. **Fontes explícitas:** o relatório exibe a origem simulada e a data de cada informação. Scores de provedores não são agregados matematicamente.
3. **PHP sem framework:** evidencia a implementação REST e uso de PDO sem ampliar dependências no protótipo.
4. **MySQL relacional:** empresas, pessoas, participações, vínculos e registros são modelados separadamente.
5. **Consulta registrada via POST:** a seleção da empresa aciona automaticamente o POST de verificação. Requisições GET permanecem sem efeitos colaterais.
6. **Local-first:** serviços Docker restritos a localhost e sem dados verdadeiros.

## Parte 2 — Árvore societária

1. **Consulta dedicada:** `GET /api/empresas/{id}/arvore` retorna a empresa, seus sócios e demais empresas relacionadas por pessoas, com percentuais exatos.
2. **Sem N+1 queries:** a resposta executa três consultas SQL independentemente da quantidade de sócios.
3. **Leitura de vínculos:** empresa ↔ pessoa ↔ outras empresas indica participações reais da base fictícia, não uma relação de subsidiária ou controle.
4. **Interação:** cada ramo é recolhível, sócios e empresas mostram detalhes e a navegação conserva a aba da árvore.
5. **Sem dependência visual adicional:** a árvore utiliza Vue e CSS; não precisa de biblioteca de grafos.

## Parte 3 — Integrações REST simuladas

1. **Provedor separado:** `mock-bureaus` é um serviço HTTP independente na rede interna Docker. Assim, a API principal faz uma requisição externa real em termos de protocolo, sem credenciais reais.
2. **Cenários por provedor:** os testes de integração selecionam normal, lento, erro 503 ou timeout. A interface de uso comum não expõe cenários técnicos.
3. **Timeout explícito:** o consumidor cURL limita cada tentativa a 1,6 s; o serviço simulado retarda a resposta por 3 s no cenário `timeout`.
4. **Falha parcial:** a execução é gravada mesmo quando um provedor falha. Cada resposta tem um status próprio; o HTTP 201 indica apenas que a tentativa de integração foi registrada.
5. **Snapshots sem perda:** uma consulta bem-sucedida atualiza o indicador local correspondente; erros não apagam snapshots anteriores. A origem e a data do conteúdo permanecem explícitas.
6. **Persistência de auditoria:** execução e resultados ficam em tabelas próprias, separadas dos indicadores utilizados na tela.
7. **Contrato estrito:** provedor, código de demonstração, campos numéricos, score opcional e flag de simulação são validados antes de persistir o payload.
8. **Sem inferência de risco:** os scores de bureaus distintos não são somados, classificados ou usados para aprovar crédito.

**Escopo não implementado:** autenticação, testes end-to-end completos com MySQL e Docker, rate limiting e exportação de relatórios. Estes itens ficam fora do protótipo local inicial.

## Parte 4A — Relatório consolidado

- O relatório é montado sob demanda por `ConsolidatedReportService`, reutilizando os repositórios e o histórico das integrações. Não há nova tabela para evitar snapshots duplicados.
- A última execução REST é informada separadamente do indicador salvo por fonte; a falha de uma integração não elimina o último dado conhecido e fica explicitamente sinalizada.
- Não se calcula score médio, classificação de crédito ou conclusão automatizada. Percentuais societários são apenas participações da empresa pesquisada.
- A exportação PDF utiliza `window.print()` no navegador e folha de estilo `@media print` (A4) para evitar dependências e serviços adicionais na prova de conceito.
- A rota devolve JSON e apenas a interface oferece exportação em PDF; não se afirma que a API retorna um PDF.

## Parte 5 — Minimalismo funcional e acessibilidade

- A preferência visual do autor é **minimalista por uma decisão pessoal baseada em acessibilidade**. Não são necessários glow, gradientes ornamentais, animações, bordas em cada seção ou tipografia microscópica para demonstrar engenharia de software.
- A interface usa cores neutras, texto mais legível, espaços consistentes e indicadores de seleção sem sobrecarregar a página.
- O fluxo com teclado é parte dos requisitos: pular ao conteúdo, selecionar entidades por botões nativos, mover-se pelas abas com setas/Home/End e operar todos os nós societários sem mouse.
- O foco explícito e os rótulos visíveis têm prioridade sobre estilização; a árvore mantém linhas de conexão e bordas apenas onde comunicam relações e áreas acionáveis.
- Os textos do relatório foram preservados e a impressão continua com estilo próprio para A4.
- Não há alegação de conformidade WCAG certificada; a auditoria manual com tecnologias assistivas permanece necessária.

## Parte 6 — Interface focada no usuário e histórico automático

- A aba de laboratório REST e seus controles foram removidos do frontend. Permanecem backend, mock-bureaus, contrato das APIs e testes de falhas.
- Ao selecionar uma empresa, o frontend chama `POST /api/empresas/{id}/verificacoes`. O backend grava um registro de consulta, executa a coleta de duas fontes e devolve o perfil atualizado; a interface recarrega o histórico automaticamente.
- GET não grava registros, reduzindo efeitos colaterais inesperados. O endpoint técnico antigo continua disponível para testes, mas não aparece na navegação principal.
- Avisos de dados fictícios estão apenas no rodapé da página e do documento impresso. Nomes de fontes no frontend omitem identificadores de demonstração, enquanto o README e o rodapé deixam explícitos os limites do projeto.
- A apresentação das fontes no relatório usa estados compreensíveis (atualizado ou preservado) em vez de códigos HTTP e tempos de resposta.

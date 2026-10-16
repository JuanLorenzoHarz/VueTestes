# Design minimalista e acessibilidade — Nexus v0.6

## Critério de projeto

A preferência do autor por um **design minimalista é uma escolha pessoal fundamentada em acessibilidade**. O Nexus não pretende ser uma vitrine de efeitos gráficos. O objetivo é que qualquer usuário consiga identificar onde está, ler os dados com conforto e operar as funções principais com segurança e previsibilidade.

Isso implica:

- Sem efeitos de glow, brilho, gradientes ornamentais ou animações chamativas.
- Tipografia do sistema, tamanho base de 16 px, textos de interface geralmente de 13 a 16 px, com linhas e espaços consistentes.
- Paleta de baixa saturação, com contraste como requisito para texto e comandos.
- Bordas apenas onde identificam campos, nós de relação, tabelas, divisórias informativas ou elementos cuja distinção é necessária.
- Conteúdo informativo primeiro; ícones são secundários e não podem ser o único sinal de ação ou estado.
- Elementos nativos HTML (`button`, `input`, `select`, `a`) antes de soluções customizadas.

## Orientação e navegação pelo teclado

| Ação | Como executar |
|---|---|
| Saltar para o conteúdo | Pressione `Tab` ao entrar na página e ative **Pular para o conteúdo principal** com Enter |
| Pesquisar empresa | Use Tab até o campo rotulado; digite; Enter para pesquisar |
| Selecionar empresa | Use Tab até o botão da entidade e Enter/Espaço para abrir |
| Trocar abas | Use Tab até a aba ativa; **←** e **→** mudam a aba, **Home** vai à primeira e **End** à última |
| Acessar o conteúdo da aba | Pressione Tab a partir da aba ativa |
| Explorar a árvore societária | Use Tab para selecionar nós e controles; Enter/Espaço para ativar |
| Expandir vínculos | Ative **outra(s) participação(ões)**. O estado expandido é indicado por `aria-expanded` |
| Consultar/fechar detalhes | Ative um nó; use Tab até **Fechar detalhes da seleção**; após fechar, o foco volta ao nó |
| Rolar a árvore horizontalmente | Posicione o foco na região da árvore e use as setas; em dispositivos móveis, use rolagem normal |
| Salvar PDF | Na aba Relatório, use o botão **Imprimir / salvar PDF** e escolha a impressora PDF do navegador |

O foco do teclado utiliza um contorno visível; nenhum controle deve depender exclusivamente de mouse, hover ou cor para ser entendido. Em telas menores, a navegação de abas permanece disponível por rolagem horizontal. Não há rolagem suave programática.

## Pontos de implementação

- `frontend/src/App.vue`: link de salto, cabeçalhos, rótulo visível da pesquisa, botões de entidades com `aria-pressed`, abas com roving `tabindex` e `aria-selected`, `role=tabpanel`, mensagens de status e erros anunciáveis.
- `frontend/src/components/ArvoreSocietaria.vue`: região de rolagem identificada, botões semânticos, `aria-expanded`, `aria-pressed` e devolução do foco após fechamento dos detalhes.
- `frontend/src/App.vue`: a seleção de empresa inicia a verificação e a atualização do histórico automaticamente, com estado de andamento anunciado em `role=status`.
- `frontend/src/components/RelatorioConsolidado.vue`: cabeçalhos e tabelas semânticas, botão de impressão nativo e aviso sobre dados de demonstração apenas no rodapé do documento.
- `frontend/src/style.css`: paleta neutra, campos com altura mínima de 44 px, foco destacado, adaptação responsiva e respeito a `prefers-reduced-motion`.

## Checklist manual antes de apresentar

1. No Chrome ou Firefox, percorra a aplicação **sem mouse** (Tab, Shift+Tab, Enter, Espaço e setas). Confirme que não há armadilhas de foco.
2. Abra todas as seis abas; selecione outras empresas; navegue por sócios e participações; feche um painel de detalhes e confira se o foco retorna ao botão original.
3. Aplique zoom de **200% e 400%**. Verifique se conteúdos e ações continuam alcançáveis, inclusive pela rolagem horizontal na árvore e nas tabelas do relatório.
4. Confira foco visível em campos, botões, abas e região rolável, além de estados de erro e carregamento.
5. Revise contraste em estados normais, hover, foco, selecionado e desabilitado com uma ferramenta de contraste.
6. Teste com leitor de tela (NVDA/VoiceOver/TalkBack): títulos, aba selecionada, expansão de vínculos, mensagens e leitura das tabelas.
7. Salve um relatório em A4 e confira legibilidade de títulos, fontes, tabelas e quebra de páginas.
8. Execute a suíte automatizada: `docker compose exec frontend npm test` e `docker compose exec frontend npm run build`.

**Estado da validação:** revisão de marcação/CSS e testes automatizados escritos; testes Vue não executados neste ambiente por falta de acesso ao npm. Não se afirma conformidade WCAG 2.2 AA sem a auditoria prática acima.

## Menos ruído informativo

A navegação principal não apresenta controles de testes de integração, indicadores HTTP ou explicações repetidas sobre dados artificiais. A natureza não real do sistema está clara no rodapé da página e no rodapé de impressão. As datas e a origem dos indicadores continuam acessíveis, pois são relevantes à compreensão dos dados.

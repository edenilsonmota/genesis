# Genesis+ — identidade visual

Esta é a referência obrigatória para novas telas Blade e componentes do Genesis+. A identidade é aplicada pelos tokens de `resources/css/app.css`; novas interfaces não devem introduzir cores hexadecimais locais nem recriar paletas paralelas.

## Fundamentos

- Marca: símbolo oficial em `public/images/logo.png`, sempre com proporção preservada (`object-contain`) e texto alternativo `Logo Genesis+`.
- Fonte da interface: Instrument Sans, com a pilha sem serifa definida no tema.
- Fundo da aplicação: `surface-page` (`#F7FAFF`); superfícies agrupadas usam `surface-muted` (`#EEF5FF`) e cards usam `surface-card` (`#FFFFFF`).
- Bordas padrão: `border-default` (`#D8E5F4`).
- Texto: `text-primary` (`#0F172A`), `text-secondary` (`#526178`) e `text-disabled` (`#94A3B8`).

## Paleta oficial

| Papel | Token | Valor |
| --- | --- | --- |
| Ação principal | `brand-primary` | `#0051F5` |
| Hover da ação | `brand-primary-hover` | `#003EE5` |
| Estado ativo | `brand-primary-active` | `#0031D1` |
| Fundo de ação secundária | `brand-primary-soft` | `#E6F2FF` |
| Destaque ciano | `brand-cyan` | `#2BD9FB` |
| Destaque azul-claro | `brand-sky` | `#10B0FB` |
| Destaque azul | `brand-blue` | `#088CF3` |
| Sucesso | `success` / `success-soft` | `#16A36A` / `#E8F8F0` |
| Aviso | `warning` / `warning-soft` | `#D97706` / `#FFF7E6` |
| Erro | `danger` / `danger-soft` | `#DC3545` / `#FDECEE` |
| Informação | `info` / `info-soft` | `#088CF3` / `#E8F6FF` |

Controles desabilitados usam `border-default` como fundo e `text-disabled-control` (`#8292A8`) no texto; textos informativos desabilitados mantêm `text-disabled` (`#94A3B8`).

O gradiente institucional é `brand-gradient`: `linear-gradient(135deg, #2BD9FB 0%, #088CF3 45%, #0051F5 70%, #0031D1 100%)`. Ele é reservado a superfícies de destaque e detalhes decorativos; não deve comprometer a leitura de textos longos.

## Componentes de interface

- Login: em telas grandes usa composição dividida, com `public/images/login-banner.png` no painel esquerdo e formulário compacto no painel direito. A imagem recebe sobreposição escura para preservar contraste do logo e do texto; abaixo de `lg`, o painel visual é ocultado e o formulário ocupa a tela. O campo de senha oferece controle acessível para mostrar ou ocultar o conteúdo. A troca obrigatória de senha continua no layout centralizado para concentrar a atenção na ação de segurança.
- Cards: usar `ui-card` para agrupar dados, tabelas e formulários. O raio é amplo, a sombra é discreta e a separação depende de `border-default`, não de cinzas arbitrários.
- Campos: usar `ui-label`, `ui-input` e `ui-select`. Campos mantêm fundo claro, borda azul suave e foco visível em ciano. Erros usam `text-danger` e resumos usam `ui-alert-error`.
- Botões: `ui-button-primary` para a ação principal da tela; `ui-button-secondary` para ações de menor ênfase; `ui-button-outline` para cancelar, voltar e ações neutras; `ui-button-danger` apenas para operações destrutivas ou de inativação.
- Badges: estados sempre incluem texto, além de cor. O componente `x-status-badge` é a fonte padrão para status de entidades.
- Tooltips: para esclarecer termos ou ícones sem poluir o formulário, usar `x-tooltip` com texto breve e objetivo. Ele funciona ao passar o mouse e ao receber foco pelo teclado; não usar tooltip como única forma de apresentar uma instrução essencial.
- Tabelas: cabeçalho com `surface-muted`, texto secundário e borda padrão; registros preservam contraste alto. Em telas pequenas, a informação deve migrar para cards em vez de forçar rolagem horizontal desnecessária.
- Paginação: o template local usa a ação principal na página ativa, estados desabilitados legíveis e foco nítido.
- Matriz de permissões por cargo: os módulos são agrupados exatamente pelas categorias da sidebar — **Principal**, **Cadastros**, **Administração** e **Financeiro** — e na mesma ordem. Cada registro representa uma tela/capacidade do backend e apresenta nome, descrição e as três escolhas mutuamente exclusivas — Sem acesso, Leitura e Escrita. A relação de chaves, telas e categorias está em `docs/genesis-implementation-context.md`.

## Gráficos e visualização de dados

Apache ECharts é a biblioteca padrão para gráficos interativos. Deve ser importada de forma modular e carregada somente nas telas que exibem gráficos; não usar CDN em produção nem introduzir outra biblioteca sem uma decisão arquitetural explícita.

- Paleta dos gráficos: azul principal `#0051F5`, azul claro `#2BD9FB`, entradas/sucesso `#16A34A`, pendências/alerta `#F59E0B` e saídas/erro `#EF4444`.
- Barras usam gradiente vertical, sombra discreta e cantos superiores amplamente arredondados; barras horizontais arredondam a extremidade de valor.
- Linhas são suaves, com terminação arredondada, marcadores circulares e preenchimento em gradiente de baixa opacidade quando fizer sentido para a leitura.
- Roscas e pizzas usam separação branca entre fatias, `padAngle` e bordas arredondadas; preferir rosca a pizza quando houver categorias e legenda.
- Legendas nunca podem disputar espaço com eixos ou rótulos: ficam abaixo da área de plotagem, com espaço reservado no `grid.bottom`, ícone `roundRect` e texto legível. Quando houver muitos itens, usar legenda rolável ou adaptar a orientação.
- Preservar tooltip em português do Brasil, valores em real brasileiro, `aria.enabled` e `ResizeObserver` para acessibilidade e responsividade. Rótulos extensos devem truncar, girar ou ganhar mais espaço de grade antes de sobrepor conteúdo.

## Agenda

FullCalendar é a biblioteca padrão para calendários e agendas. Deve ser instalado pelo npm, empacotado pelo Vite e carregado apenas na tela que contém o calendário. O Genesis+ usa os plugins padrão de ano em mini-meses, mês, semana/dia com horários, lista e interação; plugins Scheduler/Premium não fazem parte da arquitetura atual.

- localização, botões e datas permanecem em português do Brasil;
- controles usam azul principal `#0051F5`, foco ciano `#2BD9FB`, bordas e superfícies dos tokens globais;
- rascunhos usam alerta `#F59E0B`, cancelados usam erro `#EF4444` com texto riscado e opacidade reduzida;
- o seletor Ano/Mês/Semana/Dia/Lista fica fora da toolbar interna para evitar quebra em telas estreitas;
- eventos só exibem affordance de arrastar ou redimensionar quando a API informa permissão de edição;
- selecionar um período inicia o cadastro, clicar abre detalhes e uma falha no backend restaura imediatamente a posição anterior;
- cores nunca substituem os rótulos de tipo, status e visibilidade nos detalhes e legendas.

## Estados de interação

- **Hover:** botões e links reforçam a ação com `brand-primary-hover`, `surface-muted` ou borda `brand-sky`, conforme a sua hierarquia; não usam mudanças bruscas de layout.
- **Foco:** todo controle recebe contorno visível em `brand-cyan`, com afastamento do elemento. O foco deve permanecer perceptível também em links de ícone, paginação e controles do drawer.
- **Ativo/selecionado:** a ação principal usa `brand-primary-active`; páginas e itens de menu selecionados usam a primária com fundo suave e `aria-current` quando aplicável.
- **Desabilitado:** não responde a interação, usa `border-default` no fundo e `text-disabled-control`; a interface explica o motivo quando isso não estiver evidente.

## Navegação e responsividade

A fonte única dos itens da navegação é `resources/views/components/navigation/sidebar-links.blade.php`; ela é renderizada nos contextos desktop e móvel para que rotas, permissões e estados ativos não se desviem.

- Em `lg` ou maior, a sidebar permanece compacta em 64px e expande para 224px ao receber hover ou foco do teclado. Não existe botão ou chevron exclusivo para expandir a sidebar; chevrons são reservados às categorias.
- Recolhida, ela mostra o símbolo e os ícones centralizados; expandida, mostra o nome `Genesis+`, os rótulos e as categorias. O usuário permanece no topo direito do cabeçalho, nunca no rodapé da sidebar.
- A categoria **Cadastros** é expansível e guarda a preferência em `genesis.sidebar.registrations.open`. Uma rota de Área e Igrejas, Membros ou Importação de membros mantém a categoria aberta para evidenciar o contexto atual.
- A categoria **Administração** é expansível e guarda a preferência em `genesis.sidebar.administration.open`. Ela contém, nesta ordem, Usuários, Cargos e permissões, Departamentos e Auditoria. A matriz pertence à tela de cargos e sua taxonomia de módulos reproduz as categorias da sidebar.
- A categoria **Financeiro** é expansível, guarda a preferência em `genesis.sidebar.finance.open` e exibe, nesta ordem e conforme a permissão, **Visão financeira**, **Movimentações** e **Dízimos**. Contas e categorias continuam como infraestrutura interna do domínio financeiro.
- A sidebar e o drawer usam `surface-card` como fundo, com texto escuro, bordas suaves e estado ativo em `brand-primary-soft`. O gradiente institucional fica reservado aos destaques de conteúdo e às visualizações de dados previstas nesta identidade.
- Abaixo de `lg`, a navegação usa o drawer do Flowbite, com backdrop, fechamento por Escape, bloqueio de rolagem, foco inicial e ciclo de Tab. O botão de abertura comunica o estado por `aria-expanded`.
- Layouts e formulários devem ser revisados, no mínimo, em 1440px, 1024px, 768px e 390px. Não ocultar uma ação essencial apenas porque a largura diminuiu.

## Acessibilidade e evolução

Todo elemento interativo deve preservar foco visível em ciano, rótulos acessíveis e contraste suficiente. Ícones isolados precisam de `aria-label` ou texto alternativo; cores de status não podem ser o único sinal da informação. Respeitar `prefers-reduced-motion` e não criar transições que bloqueiem navegação por teclado.

O Flowbite livre é instalado pelo npm e integrado ao Vite/Tailwind, exclusivamente para comportamentos e componentes compatíveis com esta linguagem visual. Componentes Pro, CDN em produção e estilos globais que substituam os tokens do Genesis+ não são permitidos.

Máscaras de campos são implementadas com `IMask`, empacotado pelo Vite. Todo novo campo monetário deve usar `data-currency-input`: a interface apresenta `R$ 10,00` e o JavaScript envia o valor numérico normalizado ao backend. Campos de hora devem usar `data-time-24`, `type="text"` e `inputmode="numeric"`, exibindo `HH:mm` em 24 horas e nunca AM/PM. A validação no servidor continua obrigatória.

Todos os campos simples de data usam Flatpickr como padrão global (`resources/js/date-inputs.js`), com calendário e valor alternativo em português do Brasil (`dd/mm/aaaa`). O valor submetido continua sendo `YYYY-MM-DD`; novos campos de data devem usar `type="date"` para aderir automaticamente ao padrão.

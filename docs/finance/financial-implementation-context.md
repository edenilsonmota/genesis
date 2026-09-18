# Financeiro — contexto de domínio e implementação

## Limite do produto

O Financeiro do Genesis+ é um controle gerencial de entradas, saídas e saldos. Ele não substitui contabilidade formal, escrituração fiscal ou demonstrações contábeis regulamentadas. “Balancete” fica reservado a um relatório futuro; o painel do módulo será denominado **Visão financeira**.

Estão implementados `financial_accounts`, `financial_categories`, `financial_transactions`, `financial_movements`, o fluxo de Dízimos e a Visão financeira. Entradas, saídas e transferências podem ser salvas como rascunho, pendência ou liquidadas; somente movimentos liquidados compõem o saldo. Relatórios e anexos permanecem planejados.

## Escopos e contas

Uma conta financeira pertence exatamente à área ou exatamente a uma igreja. A exclusividade é validada na aplicação e por constraint PostgreSQL. A conta de igreja não repete `area_id`, pois sua área é derivada da própria igreja.

Não há tela nem permissão de administração de contas nesta fase. Ao criar uma Área, o sistema cria o seu `CAIXA DA ÁREA`; ao criar uma Igreja, cria o seu `CAIXA DA IGREJA`. Esses caixas são marcados como padrão e a base impede mais de um caixa padrão por escopo. Instalações já existentes recebem um caixa padrão durante a migration, aproveitando a primeira conta já cadastrada quando houver. Isso simplifica o lançamento diário da igreja sem eliminar a possibilidade futura de contas bancárias ou caixas de departamentos.

Não existe coluna editável de saldo ou saldo inicial. O saldo será calculado pela soma dos movimentos confirmados. Quando implementado, um saldo inicial será uma movimentação de abertura auditável, nunca um número sobrescrito diretamente na conta.

## Categorias

Categorias pertencem à única área e são compartilhadas por suas igrejas. Cada categoria classifica uma entrada (`income`) ou saída (`expense`). O mesmo nome pode existir nos dois tipos, mas não se repete sem diferenciar maiúsculas e minúsculas dentro da combinação área e tipo. Não existem subcategorias nem vínculo da categoria com igreja ou departamento.

Não existe tela nem permissão exclusiva de categorias. A Área recebe categorias padrão fixas ao ser criada: 11 de entrada (incluindo Dízimos, Ofertas e Doações) e 20 de saída (incluindo Energia elétrica, Aluguel e Missões). No formulário de Movimentações, o Choices filtra as opções pelo tipo selecionado e permite criar uma categoria personalizada quando a pesquisa não encontrar resultado. A categoria criada herda obrigatoriamente o tipo da movimentação e a Área da conta selecionada; a operação é auditada.

Transferência será um tipo próprio de transação e não uma categoria de entrada ou saída. Departamentos poderão ser associados diretamente a lançamentos futuros apenas como classificação gerencial; continuarão sem conceder acesso.

## Movimentos, consolidação e responsabilidade futura

Uma transação representa o fato gerencial — entrada, despesa, transferência ou estorno — e gera movimentos nas contas. Transferências produzem movimentos opostos e atômicos entre duas contas, sem receita ou despesa artificial. A Visão financeira consolida contas da área e das igrejas conforme autorização, sempre a partir desses movimentos.

A Visão financeira consolida os movimentos liquidados por ano e pelo contexto da igreja ativa. No contexto **Visão geral**, exclusivo do administrador global, agrega todas as contas da área e das igrejas. Os gráficos usam Apache ECharts, importado modularmente e carregado apenas nesta tela. Seguem o padrão visual documentado em `docs/visual-identity.md`: gradientes, cantos amplos, sombras sutis, linhas suaves e legendas arredondadas fora da área dos eixos. Os agregados ficam em cache por cinco minutos e usam o store padrão do Laravel; produção configura `CACHE_STORE=redis`. Qualquer mutação financeira incrementa a versão do cache para que a próxima leitura seja recalculada imediatamente.

O usuário responsável pelo lançamento será preservado para rastreabilidade. Alterações relevantes usarão a auditoria existente. Cancelamentos e estornos serão operações explícitas que preservam histórico; registros financeiros confirmados não serão simplesmente apagados ou reescritos.

Nos formulários de Movimentações, **Responsável** é o membro que responde ou acompanha o lançamento; **Contraparte** é a pessoa, empresa ou instituição do outro lado do pagamento ou recebimento. Ambos possuem tooltip objetivo na interface. Valores monetários usam IMask com apresentação em real brasileiro (`R$ 10,00`) e são normalizados antes da validação do backend.

## Dízimos e privacidade

Dízimos possuem Controller e telas próprios, separados de Movimentações para oferecer um fluxo adequado à igreja. Essa separação é somente de interface: cada dízimo é uma entrada liquidada associada ao caixa padrão da igreja, possui data de recebimento e mês de referência, afeta o mesmo saldo e aparecerá nos relatórios consolidados.

O membro contribuinte é dado financeiro sensível. A tela exige a permissão específica `finance.tithes`, restringe membros e lançamentos à igreja autorizada e mantém auditoria sem registrar conteúdo desnecessário. Não há tabela de saldo exclusiva para dízimos.

## Autorização atual

A capacidade `finance.transactions` usa os níveis existentes `read` e `write`, herdados dos cargos na igreja ativa. O administrador global mantém o bypass atual. Contas e categorias são infraestrutura do lançamento: a autorização verifica o caixa do seu escopo e a criação rápida de categoria exige escrita em Movimentações. As demais chaves financeiras já existem como capacidades reservadas, mas ainda não possuem rotas ou links funcionais.

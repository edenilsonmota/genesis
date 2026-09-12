# Financeiro — contexto de domínio e implementação

## Limite do produto

O Financeiro do Genesis+ é um controle gerencial de entradas, saídas e saldos. Ele não substitui contabilidade formal, escrituração fiscal ou demonstrações contábeis regulamentadas. “Balancete” fica reservado a um relatório futuro; o painel do módulo será denominado **Visão financeira**.

Nesta primeira etapa somente `financial_accounts` e `financial_categories` estão implementadas. Transações, movimentos, transferências, dízimos, relatórios e anexos permanecem planejados.

## Escopos e contas

Uma conta financeira pertence exatamente à área ou exatamente a uma igreja. A exclusividade é validada na aplicação e por constraint PostgreSQL. A conta de igreja não repete `area_id`, pois sua área é derivada da própria igreja. Contas da área representam recursos administrativos ou consolidados e são gerenciadas somente no escopo global; usuários locais consultam e administram apenas contas da igreja ativa.

Não existe coluna editável de saldo ou saldo inicial. O saldo será calculado pela soma dos movimentos confirmados. Quando implementado, um saldo inicial será uma movimentação de abertura auditável, nunca um número sobrescrito diretamente na conta.

## Categorias

Categorias pertencem à única área e são compartilhadas por suas igrejas. Cada categoria classifica uma entrada (`income`) ou saída (`expense`). O mesmo nome pode existir nos dois tipos, mas não se repete sem diferenciar caixa dentro da combinação área e tipo. Não existem subcategorias nem vínculo da categoria com igreja ou departamento.

Transferência será um tipo próprio de transação e não uma categoria de entrada ou saída. Departamentos poderão ser associados diretamente a lançamentos futuros apenas como classificação gerencial; continuarão sem conceder acesso.

## Movimentos, consolidação e responsabilidade futura

Uma transação futura representará o fato gerencial — entrada, despesa, ajuste ou transferência — e gerará movimentos nas contas. Transferências deverão produzir movimentos opostos e atômicos entre duas contas, sem receita ou despesa artificial. A Visão financeira consolidará contas da área e das igrejas conforme autorização, sempre a partir desses movimentos.

O usuário responsável pelo lançamento será preservado para rastreabilidade. Alterações relevantes usarão a auditoria existente. Cancelamentos e estornos serão operações explícitas que preservam histórico; registros financeiros confirmados não serão simplesmente apagados ou reescritos.

## Dízimos e privacidade

Dízimos terão Controller, Service e telas próprios, separados de Movimentações para oferecer um fluxo adequado à igreja. Essa separação é somente de interface: cada dízimo será uma entrada associada a uma conta, terá recebimento e mês de referência, afetará o mesmo saldo e aparecerá nos relatórios consolidados.

Quando informado, o membro contribuinte será dado financeiro sensível. A futura implementação deverá aplicar autorização específica, minimizar sua exposição em listagens e exportações e manter auditoria sem registrar conteúdo desnecessário. Não haverá tabela de saldo exclusiva para dízimos.

## Autorização atual

As capacidades `finance.accounts` e `finance.categories` usam os níveis existentes `read` e `write`, herdados dos cargos na igreja ativa. O administrador global mantém o bypass atual. As demais chaves financeiras já existem como capacidades reservadas, mas ainda não possuem rotas ou links funcionais.

# Importação de membros — segurança e fluxo operacional

## Ciclo de vida

```text
uploaded → validating → awaiting_confirmation → processing → completed
                     ↘ validation_failed        ↘ processing_failed
```

1. A requisição valida permissão, igreja, extensão/MIME e tamanho, grava no disco privado e calcula SHA-256.
2. `ValidateMemberImportJob` lê e valida a planilha sem criar ou alterar membros.
3. CEPs distintos são normalizados e consultados antes de qualquer transação de gravação; o serviço atual mantém cache por sete dias.
4. Qualquer linha inválida bloqueia o arquivo inteiro. O gestor corrige e faz um novo envio.
5. Somente `awaiting_confirmation` sem erros pode ser confirmado uma vez.
6. `ProcessMemberImportJob` revalida CPF, membro e vínculo sob lock e persiste o arquivo atomicamente.
7. Falha definitiva do Job atualiza o histórico; não existe reprocessamento cego pela interface.

## Redis e worker

Os dois Jobs fixam explicitamente:

```text
connection: redis
queue: imports
tries: 3
Validate timeout: 300 s; backoff: 30, 120, 300 s
Process timeout: 600 s; backoff: 60, 180, 600 s
retry_after: 660 s
```

`QUEUE_CONNECTION=redis` é o padrão dos exemplos local e de produção. A aplicação recusa inicialização em produção quando o driver configurado é `sync`. O Compose local sobe um worker com:

```bash
php artisan queue:work redis --queue=imports,default --sleep=1 --tries=3 --timeout=600 --max-time=3600
```

Em produção, mantenha processo equivalente sob Supervisor, systemd ou orquestrador e reinicie workers após deploy (`php artisan queue:restart`). Monitore `failed_jobs`. Uma falha de validação exige novo upload; uma falha de processamento é integralmente revertida e também exige uma nova importação até existir um fluxo explícito de retomada.

## Dados e auditoria

- arquivos ficam em `storage/app/private/member-imports`, nunca no disco público;
- downloads passam por rota autenticada e Policy;
- relatórios mascaram CPF e omitem telefone/e-mail em valores de erro;
- `normalized_payload` guarda apenas os campos necessários ao processamento;
- auditoria registra modelo, exportação, upload, validação, confirmação, processamento e falhas, com igreja, importação, hash e totais;
- senha, token, CPF completo em auditoria e conteúdo binário nunca são registrados.

## Endereço e atualização conservadora

O cadastro atual exige `city_id`; por isso novos membros exigem CEP válido. O IBGE retornado pelo serviço deve existir no snapshot de cidades. Em atualizações, CPF vazio preserva o CPF atual e CEP vazio preserva CEP, número, complemento, rua, bairro e cidade. CEP informado substitui apenas dados resolvidos com segurança; rua ou bairro ausente na resposta não apaga o valor existente.

## Limitações deliberadas

Não há CSV, Google Sheets, imagens, logins, cargos, permissões, departamentos, finanças, troca automática de igreja, exclusão, processamento parcial ou retomada manual. Esses itens exigem decisão de produto e revisão de segurança futuras.

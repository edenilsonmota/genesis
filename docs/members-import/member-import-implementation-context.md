# Importação de membros — contexto de implementação

## Objetivo e escopo

O módulo **Cadastros → Importação de membros** cria ou atualiza somente dados cadastrais básicos de membros por arquivo `.xlsx`. Cada importação pertence a uma igreja e nunca altera igreja, vínculo existente, usuário, cargo, departamento, permissão ou dado financeiro.

A permissão é `members.import`: leitura permite consultar tela, histórico, arquivos autorizados e modelo em branco; escrita permite upload e confirmação. A exportação preenchida também exige leitura de `members`. Todo `church_id` é validado no backend pelo `PermissionService` e pela `MemberImportPolicy`.

## Planilha

A aba importável chama-se `Membros` e possui exatamente, nesta ordem:

```text
id_membro,nome,cpf,email,telefone,data_nascimento,sexo,cep,numero,complemento
```

- linha sem `id_membro`: criação;
- linha com `id_membro`: atualização do membro ativo já vinculado à igreja;
- sexo aceita as normalizações explícitas `M`/`F`, `male`/`female` e `Masculino`/`Feminino`;
- data aceita célula de data real do Excel ou texto `dd/mm/aaaa`;
- fórmulas são rejeitadas e nunca avaliadas;
- strings exportadas iniciadas por `=`, `+`, `-` ou `@` recebem apóstrofo para impedir formula injection;
- limite: 10 MB e 5.000 linhas preenchidas; linhas totalmente vazias são ignoradas.

O modelo em branco mantém exemplos fictícios apenas na aba `Instruções`. A exportação para atualização contém apenas membros ativos da igreja autorizada e campos indispensáveis.

## Componentes

- `MemberImportController`: consulta, downloads, upload e confirmação;
- `MemberImportService`: armazenamento privado, criação do histórico e despacho;
- `MemberImportSpreadsheetService`: geração/leitura XLSX e relatório de erros;
- `MemberImportValidationService`: normalização, CEP, escopo e prévia persistida;
- `MemberImportProcessingService`: revalidação e gravação atômica;
- `ValidateMemberImportJob` e `ProcessMemberImportJob`: execução assíncrona na fila `imports` da conexão `redis`;
- `member_imports` e `member_import_rows`: histórico e resultado imutável da validação.

O processamento de até 5.000 registros percorre blocos de 250, mas mantém uma transação única no arquivo. Essa decisão prioriza a atomicidade requerida: uma exceção desfaz todas as criações, atualizações, vínculos e estados de linha.

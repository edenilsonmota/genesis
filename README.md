# Genesis

## Catálogo de estados e municípios

O catálogo geográfico é mantido no snapshot versionado `database/data/ibge-localities.json`. Para atualizá-lo a partir da API oficial de Localidades do IBGE:

```bash
./vendor/bin/sail artisan ibge:download-localities
```

Depois, revise e versione a alteração do snapshot. O `DatabaseSeeder` consome somente esse arquivo local; migrations, Seeders e testes não fazem requisições externas.

# Agenda — contexto de implementação

## Biblioteca e carregamento

A Agenda usa **FullCalendar 7**, instalado pelo npm e empacotado pelo Vite. O módulo JavaScript é carregado de forma assíncrona somente quando existe `[data-calendar]` na página; não há CDN. São usados exclusivamente plugins padrão/open source: `interaction`, `daygrid`, `timegrid`, `list` e `multimonth`, além do tema Monarch e da localização `pt-BR`.

As visualizações oficiais são:

- `multiMonthYear`: planejamento anual em 12 mini-meses responsivos;
- `dayGridMonth`: agenda mensal;
- `timeGridWeek`: semana com horários;
- `timeGridDay`: operação diária;
- `listUpcoming`: lista dos próximos 90 dias, especialmente útil em telas pequenas.

Os eventos são buscados sob demanda em `GET /calendar/feed`, usando o intervalo exclusivo `start/end` enviado pelo FullCalendar. A resposta segue o Event Object da biblioteca e informa individualmente se o evento pode ser arrastado ou redimensionado. O frontend pode solicitar alterações, mas Policies, Form Requests e Services do Laravel são sempre a fonte de verdade.

## Modelo de dados

`calendar_events` armazena área, igreja opcional, departamento e responsável opcionais, criador, título, tipo, início, término, dia inteiro, local, descrição, visibilidade, status e regras de edição do criador/responsável. O tipo é uma chave para `calendar_event_types`, catálogo por área: os tipos iniciais são provisionados automaticamente, e quem possui escrita em Agenda pode criar outro tipo pelo modal do formulário. Assim, tipos não são enum nem exigem alteração de código. A criação também é auditada.

Datas com horário são persistidas em UTC; formulários e detalhes usam `America/Sao_Paulo`, configurável por `CALENDAR_TIMEZONE`. O sistema usa horário de 24 horas (`HH:mm`) para informar e exibir horas, por exemplo `19:00` e `21:30`; não há AM/PM. Os campos de hora usam IMask (`data-time-24`), não o controle nativo do navegador, para que essa apresentação não varie conforme a localidade do dispositivo.

O término persistido para eventos de dia inteiro é exclusivo, como exige o FullCalendar. No formulário a data final continua inclusiva para o usuário. A constraint de banco exige término posterior ao início, e visibilidade departamental exige um departamento.

Não existe exclusão física pelo fluxo web. Cancelar mantém o evento na agenda e no histórico, bloqueia novas edições e registra `calendar_event.cancelled` na auditoria. Criação, atualização e reagendamento também são auditados.

## Escopo e autorização

O módulo de permissão é `calendar`: leitura permite consultar a agenda; escrita permite criar e administrar eventos do escopo ativo. O administrador global possui acesso total. Em **Visão geral**, ele consulta todas as igrejas e pode criar eventos para toda a área; com uma igreja selecionada, a agenda contém eventos dessa igreja e eventos gerais da área.

Usuários comuns sempre operam na igreja ativa validada pelo `PermissionService`. Eventos de outra igreja não são retornados pela API. Eventos gerais da área são somente leitura para administradores locais, pois uma alteração afetaria todas as igrejas.

Visibilidades:

- **Igreja:** qualquer usuário com `calendar.read` no escopo ativo;
- **Departamento:** integrantes com atribuição ativa em um cargo daquele departamento e usuários com `calendar.write`;
- **Privado:** criador, responsável e usuários com `calendar.write`.

Rascunhos são restritos ao criador, responsável e usuários com escrita, independentemente da visibilidade. Um evento de igreja pode ser editado por quem possui `calendar.write`; também pelo criador ou responsável quando a opção correspondente estiver ativa e a pessoa ainda possuir acesso ao escopo. O backend revalida essas regras em edição, cancelamento, drag-and-drop e resize.

## Interface

A Agenda usa a paleta institucional: `#0051F5` como principal, `#2BD9FB` como destaque, `#F59E0B` para rascunhos e `#EF4444` para cancelados. Tipos recebem cores próprias, mas status também é comunicado por texto e legenda. A toolbar compacta mantém navegação de datas separada do seletor de visualização para funcionar bem no celular.

Selecionar um intervalo abre o cadastro preenchido; clicar em um evento abre seus detalhes. Arrastar ou redimensionar chama `PATCH /calendar/events/{event}/schedule`; em qualquer falha de autorização ou validação, o FullCalendar executa `revert()` e restaura a posição anterior.

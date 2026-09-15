# Ontwerp: wedstrijden inplannen over speeldagen, tafels en tijdsloten

Datum: 2026-09-15
Status: goedgekeurd ontwerp, klaar voor implementatie in fases
Issue: #57 (overkoepelend), sub-issues per fase — zie "Fasering"

## Doel

Een beheerder laat de openstaande wedstrijden van een competitie **met één actie
automatisch inplannen** op speeldag, tafel en tijdslot, op basis van de ingevulde
beschikbaarheid en de planningsinstellingen. Het resultaat is per speeldag zichtbaar
(tafels als kolommen, tijdsloten als rijen) en wedstrijden die niet ingepland konden
worden staan mét reden in een rapport. Daarna kan de beheerder handmatig bijsturen:
een wedstrijd verplaatsen (die wordt daarmee vastgezet), losmaken, of het hele schema
opnieuw laten berekenen.

Het rapport is voor de beheerder het belangrijkste deel van de feature: de planner mag
nooit stilzwijgend wedstrijden overslaan.

## Kernbeslissingen

1. **Doel van het algoritme, in rangorde**: (1) zoveel mogelijk wedstrijden inplannen,
   (2) iedere speler ongeveer evenveel wedstrijden per speeldag, (3) weinig wachttijd
   tussen de eigen wedstrijden op een avond. "Speeldag zo vroeg mogelijk klaar" is geen
   doel. Reden: een onvolledig schema kost de beheerder handwerk; eerlijkheid en
   compactheid zijn comfort.
2. **Hard versus zacht.** Hard (nooit geschonden, liever niet plannen): beschikbaarheid,
   één speler op één tafel tegelijk, de openingstijden van de speeldag, de wisseltijd
   (zit in de slotlengte) en `max_matches_per_player_per_day` (0 = onbeperkt). Zacht:
   `min_rest_minutes` — de planner probeert het, maar plant liever met te weinig rust
   dan niet, en meldt de schending in het rapport.
3. **Herplannen in twee smaken.** "Wedstrijden inplannen" vult alleen de nog ongeplande
   wedstrijden aan rond het bestaande schema (stabiel voor deelnemers). Een aparte actie
   "Opnieuw plannen", achter een bevestigingsdialoog met waarschuwing, laat alles los
   behalve gespeelde en vastgezette wedstrijden en rekent opnieuw.
4. **Onplanbaar wordt opgeslagen.** De reden komt in een kolom `scheduling_failure` op
   de wedstrijd en wordt gewist zodra hij wél een plek krijgt. Het rapport wordt uit de
   database opgebouwd en overleeft dus een refresh; de toast toont alleen aantallen.
5. **Pauze valt halverwege, op de slotgrens.** De speeldag wordt verdeeld in vaste slots
   van (wedstrijdduur + wisseltijd) minuten vanaf `starts_at`; na het middelste slot komt
   een pauzeblok van `break_duration_minutes`. Het laatste slot eindigt op of vóór
   `ends_at`. 0 minuten = geen pauze. Geen extra kolom nodig.
6. **Gespeelde wedstrijden** worden nooit verplaatst, bezetten wél hun tafel en tijd, en
   tellen mee voor de rust en het dagmaximum van beide spelers.
7. **Beschikbaarheid blijft binair per speeldag.** Intern loopt elke check door één
   methode (`isAvailable(speler, speeldag, slot)`), zodat tijdvakken per deelnemer later
   zonder herontwerp van het algoritme toegevoegd kunnen worden (apart issue).
8. **Tijden als lokale kloktijd.** `matches.starts_at` en `ends_at` worden `time`-kolommen,
   net als op `match_days`. Eindtijd wordt opgeslagen zodat een historische planning niet
   verschuift als de wedstrijdduur later wijzigt. Hierdoor raakt deze feature #33 (UTC)
   niet. Geen aparte slot-tabel: het slot is afgeleid van de tijd.
9. **Statusguard**: plannen én handmatig verplaatsen alleen bij `Active`, vastgelegd als
   `CompetitionStatus::allowsScheduling()` — één regel voor alle schrijfroutes.
10. **Alleen gespeeld en vastgezet zijn beschermd** bij opnieuw plannen; de datum van de
    speeldag speelt geen rol. Een openstaande wedstrijd op een voorbije avond mag dus naar
    een toekomstige dag verhuizen (uitgevallen avond).
11. **UI**: nieuwe tab "Schema" op de competitie-editpagina met speeldag-kiezer, grid,
    knoppen en rapport. Handmatig verplaatsen via een dialoog met keuzevelden (speeldag,
    tafel, slot); de server valideert de harde randvoorwaarden. Drag & drop is een later,
    apart issue.
12. **Poules** (`use_pools`/`pool_size`) zijn buiten scope; de planner werkt op de
    wedstrijdenlijst zoals `SyncCompetitionMatches` die oplevert, wat de bron ook is.
13. **Synchroon, geen queue.** Round robin van ~20 spelers is 190 wedstrijden over enkele
    tientallen slots; het greedy-algoritme is ruim binnen een request klaar.
14. **Determinisme is een eis**: dezelfde invoer levert hetzelfde schema. Geen
    randomisatie; alle invoer op vaste sleutels gesorteerd; gelijke scores beslist de
    iteratievolgorde.

## Algoritme

### Slotraster per speeldag (`App\Support\Scheduling\SlotGrid`)

Een pure functie op `starts_at`, `ends_at` (`H:i`) en `CompetitionSettings`, zonder
database, zodat planner en schema-weergave gegarandeerd hetzelfde raster gebruiken.

```
slot      = match_duration + buffer            (gehele minuten)
capacity  = floor((eind - begin - pauze) / slot)
            (past er geen tweede slot, dan valt de pauze weg)
voor      = ceil(capacity / 2)                 "na het middelste slot"
slot i    begint op begin + i*slot (+ pauze zodra i >= voor), duurt `slot` minuten
pauze     = [begin + voor*slot, + pauzeduur]   of geen
```

Voorbeeld 19:00–23:00, wedstrijd 20, wissel 5, pauze 15: 9 slots — 19:00, 19:25,
19:50, 20:15, 20:40, pauze 21:05–21:20, 21:20, 21:45, 22:10, 22:35 (eindigt 23:00).

Een wedstrijd op een slot krijgt `starts_at` = slotbegin en `ends_at` = slotbegin +
wedstrijdduur (zonder wisseltijd).

### Bezetting (`ScheduleBoard`)

Bezetting wordt bijgehouden als tijdsintervallen, niet als slot-indices: per (speeldag,
tafel) en per (speler, speeldag), plus een teller wedstrijden per (speler, speeldag).
Zo blokkeert een gespeelde of vastgezette wedstrijd die niet meer op het huidige raster
ligt (instellingen of openingstijden zijn gewijzigd) toch correct. Een tafel is bezet tot
en met de wisseltijd; een speler tijdens de wedstrijd.

### Harde randvoorwaarden (`PlacementValidator`)

Eén klasse, gebruikt door zowel de planner als het handmatig verplaatsen, zodat "hard"
overal hetzelfde betekent. Vaste controlevolgorde (de eerste schending is de reden):

1. tafel hoort niet bij de speeldag;
2. slot ligt niet in het raster van de speeldag (buiten openingstijden of in de pauze);
3. een speler is niet beschikbaar op de speeldag;
4. een speler zit aan het dagmaximum (gespeelde wedstrijden tellen mee);
5. een speler speelt al op dat moment;
6. de tafel is bezet.

Minimale rust is bewust géén schending — die zit in de score.

### Toewijzing (`GreedyScheduler`)

- **Volgorde van wedstrijden: meest beperkte eerst.** Per wedstrijd het aantal speeldagen
  waarop beide spelers beschikbaar zijn; sorteer oplopend, daarna op id. Paren met weinig
  gedeelde avonden krijgen hun kans vóór de capaciteit op is (doel 1).
- **Kandidaten** per wedstrijd in vaste volgorde: speeldagen (datum, begintijd, id) →
  slots (index) → tafels (positie, id). Kandidaten met een harde schending vallen af.
- **Score** (lager is beter) voor de overgebleven kandidaten:
  - rust-penalty (1000) per speler die minder dan `min_rest_minutes` tot een eigen
    wedstrijd op die dag zou hebben — domineert, zodat een slot zonder schending altijd
    wint, ook op een andere dag;
  - eerlijkheid (100) × het aantal wedstrijden dat beide spelers die dag al hebben —
    spreidt per speler over de avonden (doel 2);
  - wachttijd (1 per minuut) tot de dichtstbijzijnde eigen wedstrijd die dag, 0 voor de
    eerste wedstrijd van de dag (doel 3).
  De eerste kandidaat met de strikt laagste score wint; bij gelijke score wint dus de
  vroegste dag, het vroegste slot en de laagste tafel.
- **Diagnose** als er geen kandidaat overblijft (`SchedulingFailure`):
  `no_shared_match_day` (geen gedeelde speeldag), `max_matches_per_day_reached` (alleen
  het dagmaximum stond in de weg), anders `no_capacity` (geen vrij slot).

### Aanvullen versus opnieuw plannen (`ScheduleCompetitionMatches`)

Zelfde vorm als `SyncCompetitionMatches`: `handle(Competition, SchedulingMode)` binnen
een transactie met een rijlock op de competitie als eerste statement, zodat plannen en een
gelijktijdige deelnemersmutatie elkaar niet halverwege raken.

1. Precondities controleren (zie hieronder); geblokkeerd → resultaat met reden, niets
   geschreven.
2. Bij **opnieuw plannen**: alle openstaande, niet-vastgezette wedstrijden leegmaken
   (speeldag, tafel, tijden, reden).
3. Bezetting opbouwen uit alle volledig geplande wedstrijden (speeldag én tafel én
   begintijd gevuld) — gespeeld, vastgezet en bij aanvullen ook de gewone geplande.
4. De openstaande, niet volledig geplande wedstrijden (op id) door de planner halen.
   Een wedstrijd waarvan de tafel is verwijderd geldt dus als ongepland.
5. Plaatsingen en mislukkingen wegschrijven via de query builder — de kolommen zijn
   bewust niet mass-assignable (zie `.ai/rules/models.md`).
6. Resultaat teruggeven: modus, aantal geplaatst, aantal onaangeroerd, aantal
   overgebleven, redenen per wedstrijd en de wedstrijden met een rustschending.

### Precondities (`SchedulingBlocker`, niet in de database)

Volgorde is prioriteit; dezelfde trait bepaalt de reden voor de knop-hint in de UI en voor
de toast na indrukken, zodat die nooit uiteenlopen (het probleem dat de
herinneringenknop nu met een lang commentaar moet afdekken).

| Reden                 | Wanneer                                                        |
| --------------------- | -------------------------------------------------------------- |
| `no_match_days`       | de competitie heeft geen speeldagen                            |
| `no_fields`           | geen enkele speeldag heeft tafels                              |
| `no_availability`     | geen enkele huidige deelnemer heeft beschikbaarheid ingevuld   |
| `no_matches`          | er zijn geen openstaande wedstrijden                           |
| `nothing_to_schedule` | alleen bij aanvullen: alles is al gepland (informatieve toast) |

De statuscheck (`allowsScheduling()`) staat hiervóór, in de HTTP-laag, zoals bij de
herinneringen. Een lege competitie is geen fout maar een normale toestand; de Action gooit
daarom geen exception maar geeft een geblokkeerd resultaat terug.

## Datamodel

### `matches` (uitbreiding)

| Kolom                | Type                                  | Opmerking                                                        |
| -------------------- | ------------------------------------- | ---------------------------------------------------------------- |
| `starts_at`          | time, nullable                        | lokale kloktijd op de speeldag, `H:i`-accessor zoals `MatchDay`  |
| `ends_at`            | time, nullable                        | `starts_at` + wedstrijdduur op het moment van plannen            |
| `pinned_at`          | timestamp, nullable                   | handmatig vastgezet; herplannen laat deze wedstrijd staan        |
| `scheduling_failure` | string, nullable (`SchedulingFailure`) | reden waarom de planner deze wedstrijd niet kon plaatsen         |

Indexen: `(match_day_id, match_day_field_id, starts_at)` voor het grid per speeldag, en
een unique op `(match_day_field_id, starts_at)` als vangnet op databaseniveau tegen dubbele
tafelbezetting (NULL's conflicteren niet, dus ongeplande rijen storen niet).

`#[Fillable]` blijft alleen de score-kolommen; de planner en het verplaatsen schrijven via
de query builder met server-bepaalde waarden. `tests/Feature/MassAssignmentTest.php` krijgt
de vier nieuwe kolommen in zijn dataset.

Model: casts voor `pinned_at` en `scheduling_failure`, null-veilige `H:i`-accessors via een
gedeelde trait met `MatchDay`, helpers `isScheduled()`, `isPinned()`, `isLocked()`
(gespeeld of vastgezet). Factory-states `scheduled(...)`, `pinned()`, `unschedulable(...)`.

### Opruimen bij verwijderen van speeldag of tafel

`nullOnDelete` zet alleen de foreign key op null; de tijden zouden blijven staan. Daarom
maken `MatchDay` en `MatchDayField` in een `deleting`-hook de planning van hun openstaande
wedstrijden volledig leeg (ook `pinned_at` en de reden). Gespeelde wedstrijden houden hun
tijden als historie. De cascade speeldag → tafels vuurt geen Eloquent-events, dus de hook
op `MatchDay` dekt de tafels zelf af. `SyncCompetitionMatches` verandert niet.

### Enums

- `App\Enums\SchedulingMode`: `Fill`, `Reschedule`.
- `App\Enums\SchedulingFailure` (in de kolom): `NoSharedMatchDay`,
  `MaxMatchesPerDayReached`, `NoCapacity`.
- `App\Enums\SchedulingBlocker` (alleen resultaat/props): de vijf precondities hierboven.
- `App\Enums\CompetitionStatus::allowsScheduling()`: alleen `Active`.

## Routes & autorisatie

Alle routes in de bestaande groep `competitions/{competition}` (admin, `scopeBindings()`).
`{match}` resolvet via de scoped binding op `Competition::matches()`, zodat een wedstrijd
van een andere competitie een 404 geeft — zelfde mechaniek als `{participant}`.

| Methode | Pad                          | Naam                      | Fase | Opmerking                              |
| ------- | ---------------------------- | ------------------------- | ---- | -------------------------------------- |
| POST    | `schedule`                   | `schedule.store`          | b    | aanvullen; `throttle:6,1`              |
| POST    | `schedule/rebuild`           | `schedule.rebuild`        | c    | opnieuw plannen; `throttle:6,1`        |
| PUT     | `matches/{match}/schedule`   | `matches.schedule.update` | c    | verplaatsen → vastgezet; Form Request  |
| POST    | `matches/{match}/pin`        | `matches.pin.store`       | c    | alleen een geplande, niet-gespeelde    |
| DELETE  | `matches/{match}/pin`        | `matches.pin.destroy`     | c    |                                        |

Statusguard op alle schrijfroutes via `allowsScheduling()`: bij de knoppen als toast +
`back()` (patroon herinneringen), bij de formulieren in `authorize()` van de Form Request
(403). Een gespeelde wedstrijd kan niet verplaatst of vastgezet worden (403).

Verplaatsen: de Form Request valideert alleen de vorm (speeldag binnen de competitie,
tafel binnen die speeldag, `starts_at` als `H:i`). De domeinvalidatie (het slot bestaat in
het raster; geen harde schending volgens dezelfde `PlacementValidator` als de planner)
gebeurt in de Action onder dezelfde rijlock als het schrijven, en levert een veldfout op
`starts_at`. Te weinig rust is toegestaan en wordt als waarschuwing gemeld.

## Beheer-UI (tab "Schema" op `resources/js/pages/competitions/edit.tsx`)

- **Actiebalk**: knop "Wedstrijden inplannen" met uitleg dat alleen lege plekken gevuld
  worden en gespeelde/vastgezette wedstrijden nooit bewegen; uitgeschakeld met de
  server-bepaalde reden als hint (patroon herinneringenknop). Fase c voegt "Alles opnieuw
  plannen" toe als destructieve `ConfirmDialog`.
- **Samenvatting**: totaal, ingepland, overgebleven, gespeeld, vastgezet.
- **Speeldag-kiezer** (`Select`), standaard de eerste speeldag.
- **Grid** per speeldag: tabel met sticky eerste kolom (slottijden), tafels als kolommen,
  slots als rijen, een pauzerij over de volle breedte, per cel de twee spelers plus
  statusbadge en pin-icoon. Wedstrijden die buiten het huidige raster liggen (instellingen
  gewijzigd na het plannen) staan in een aparte lijst onder het grid.
- **Rapport**: "Niet ingepland" gegroepeerd per reden, en "Rust niet gehaald" met speler en
  aantal minuten (herleid uit de opgeslagen tijden). Vanuit het rapport is een wedstrijd
  handmatig te plaatsen (fase c).
- **Verplaatsdialoog** (fase c): drie afhankelijke keuzevelden speeldag → tafel → slot; alle
  opties zitten al in de props. Gridcel krijgt een menu met Verplaatsen / Vastzetten /
  Losmaken; gespeelde cellen zijn alleen-lezen.
- De props komen als uitgestelde prop (`Inertia::defer`) met skeleton in grid-vorm, zoals
  de wedstrijdenlijst. De tab "Wedstrijden" krijgt kolommen voor speeldag, tafel en tijd.
- UI-copy via `t()` (Engelse sleutel + NL in `lang/nl.json`); routes via Wayfinder
  (`--with-form`).

## Testen (Pest)

Pure klassen (`SlotGrid`, `GreedyScheduler`) in `tests/Unit/Scheduling/`, zonder database.
De Action en de HTTP-laag in `tests/Feature/`.

- **Hard, aantoonbaar nooit geschonden**: nooit twee tafels tegelijk; nooit op een
  onbeschikbare dag; altijd binnen de openingstijden; nooit in de pauze; dagmaximum nooit
  overschreden (0 = onbeperkt); gespeelde wedstrijd houdt plek, blokkeert het slot en telt
  mee voor rust en maximum.
- **Zacht**: voorkeur voor een slot met rust; als alleen een rust-schendend slot overblijft
  wordt er toch gepland én gemeld.
- **Kwaliteit**: alles gepland bij voldoende capaciteit; het paar met één gedeelde dag gaat
  vóór; wedstrijden van één speler verdeeld over de avonden.
- **Mislukkingen**: elke reden apart, en een onplanbare wedstrijd breekt de rest niet af;
  reden wordt gewist zodra de wedstrijd wél gepland is.
- **Modi**: aanvullen laat geplande wedstrijden ongemoeid; opnieuw plannen maakt
  openstaand/niet-vastgezet leeg en houdt gespeeld + vastgezet; een openstaande wedstrijd
  met verwijderde tafel geldt als ongepland.
- **Determinisme**: twee runs op dezelfde invoer zijn identiek, onafhankelijk van de
  aanmaakvolgorde van speeldagen en tafels.
- **Precondities**: elke blocker geeft een geblokkeerd resultaat zonder databasewijziging;
  de editpagina toont dezelfde reden in dezelfde volgorde.
- **Opruimhooks**: speeldag of tafel verwijderen wist de planning van openstaande
  wedstrijden en houdt de tijden van gespeelde.
- **HTTP**: beheerder plant (toast + kolommen), deels gepland geeft een waarschuwing met
  beide aantallen, Draft/Finished geweigerd, deelnemer 403, gast redirect; verplaatsen
  geweigerd bij elke harde schending met veldfout, toegestaan met waarschuwing bij te
  weinig rust; gespeeld niet verplaatsbaar; vreemde wedstrijd 404; pin/unpin; alle
  schrijfroutes 403 op een niet-actieve competitie.

## Buiten scope

- Poules (apart issue; `SyncCompetitionMatches` gebruikt ze ook nog niet).
- Beschikbaarheid per tijdvak (apart issue; het algoritme is er via `isAvailable` op
  voorbereid).
- Printbaar schema, het deelnemersdashboard (#59) en het competitiedashboard (#9) — die
  lezen straks de hier gevulde kolommen.
- #33 (tijdzones): de planning gebruikt lokale kloktijd op de speeldag en toont geen
  absolute momenten.
- Drag & drop in het grid; een queue voor het plannen.

## Fasering

Elke fase een eigen sub-issue en PR onder #57, na goedkeuring van dit document.

- **(a) Planner-Action zonder UI**: migratie en enums, model-uitbreiding, factory-states,
  `app/Support/Scheduling/*`, `ScheduleCompetitionMatches`, `allowsScheduling()`,
  opruimhooks, unit- en feature-tests. Levert een werkende, geteste planner die nog
  nergens wordt aangeroepen.
- **(b) Tab Schema**: route en controller voor aanvullen, props met raster en rapport,
  grid- en rapportcomponenten, vertalingen. Afhankelijk van (a).
- **(c) Bijsturen**: opnieuw plannen, verplaatsen (met vastzetten), losmaken; Form
  Requests, `MoveCompetitionMatch`, verplaatsdialoog en celmenu. Afhankelijk van (b).

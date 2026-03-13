# moodle-reportcsv

Plugin Moodle per la generazione e distribuzione di report CSV da query SQL personalizzate.

Il progetto è composto da due componenti installabili separatamente:

| Componente | Directory | Descrizione |
|---|---|---|
| `local_reportcsv` | `local/reportcsv/` | Plugin principale: pannello admin, editor SQL, pianificazione cron |
| `block_reportcsv` | `blocks/reportcsv/` | Blocco dashboard: lista report scaricabili per gli utenti |

---

## Requisiti

- Moodle 4.0 o superiore
- PHP 8.0 o superiore
- MySQL / MariaDB
- `mysql` client installato sul server (per lo script bash di export)
- Accesso SSH al server (per crontab e script bash)

---

## Installazione

> Installa sempre `local_reportcsv` **prima** di `block_reportcsv`.

### 1. Scarica i plugin

Dalla pagina [Releases](../../releases) scarica i due file zip:
- `local_reportcsv.zip`
- `block_reportcsv.zip`

Oppure clona il repository e crea gli zip manualmente:

```bash
git clone https://github.com/TUO-USERNAME/moodle-reportcsv.git
cd moodle-reportcsv

# Crea gli zip installabili
cd local  && zip -r ../local_reportcsv.zip  reportcsv/ && cd ..
cd blocks && zip -r ../block_reportcsv.zip  reportcsv/ && cd ..
```

### 2. Installa local_reportcsv

1. Vai su **Amministrazione sito → Plugin → Installa plugin**
2. Carica `local_reportcsv.zip`
3. Segui la procedura guidata di installazione

### 3. Installa block_reportcsv

1. Vai su **Amministrazione sito → Plugin → Installa plugin**
2. Carica `block_reportcsv.zip`
3. Segui la procedura guidata di installazione

### 4. Configura il plugin

Vai su **Amministrazione sito → Report → Impostazioni Report CSV** e compila:

| Campo | Descrizione | Esempio |
|---|---|---|
| Host database | Hostname MySQL | `localhost` |
| Nome database | Nome del database Moodle | `moodle_db` |
| Utente database | Username MySQL | `moodle_user` |
| Password database | Password MySQL | `••••••••` |
| Prefisso tabelle | Prefisso tabelle Moodle | `mdl_` |
| Percorso script export | Path assoluto dello script bash | `/var/www/moodle/admin/cli/moodle_export.sh` |
| Sottocartella output | Cartella in moodledata per i CSV | `reportcsv` |

Clicca **Salva**. Il file `.env` viene generato automaticamente in `moodledata/reportcsv/.env`.

### 5. Installa lo script bash

Copia lo script bash sul server e rendilo eseguibile:

```bash
cp moodle_export.sh /var/www/moodle/admin/cli/moodle_export.sh
chmod +x /var/www/moodle/admin/cli/moodle_export.sh
```

Testa l'esecuzione manuale:

```bash
REPORTCSV_ENV_FILE=/path/to/moodledata/reportcsv/.env \
  /var/www/moodle/admin/cli/moodle_export.sh \
  /path/to/moodledata/reportcsv/sql/tuaquery.sql
```

---

## Utilizzo

### Pannello amministrazione

Raggiungibile da **Amministrazione sito → Report → Gestione Report CSV**.

Il pannello è diviso in quattro sezioni:

#### Report CSV disponibili
Elenco di tutti i file CSV generati con data, dimensione, numero di righe e tasto di download/eliminazione.

#### Editor SQL
Scrivi o incolla una query SQL. Sono supportati:
- `{nome_tabella}` — sostituito automaticamente con il prefisso reale (es. `{user}` → `mdl_user`)
- `%%STARTTIME%%` / `%%ENDTIME%%` — timestamp Unix di inizio/fine del giorno precedente

Bottoni disponibili:
- **Test** — esegue la query e mostra le prime 20 righe in anteprima
- **Scarica CSV test** — scarica il CSV dell'anteprima
- **Scarica CSV** — esegue la query completa e scarica il file
- **Salva query** — salva la query come file `.sql` in moodledata

#### Query salvate
Lista dei file `.sql` salvati, con possibilità di caricarli nell'editor, scaricarli o eliminarli.

#### Pianificazione
Aggiungi job cron per eseguire automaticamente uno script di export. Il plugin scrive nel crontab dell'utente del server web.

Frequenze disponibili: ogni ora, ogni giorno, ogni settimana, ogni mese.

### Blocco dashboard

Per aggiungere il blocco alla dashboard di un utente:

1. Accedi come amministratore
2. Vai sulla dashboard dell'utente (o sulla home del sito)
3. Attiva la modalità modifica
4. Aggiungi il blocco **Report CSV**
5. Configura il blocco (solo admin):
   - **Filtro nome file** — mostra solo i CSV il cui nome contiene il testo specificato
   - **Numero massimo di file** — limita quanti file vengono mostrati (0 = tutti)

Gli utenti vedono la lista dei report con tasto di download. Non hanno accesso alle impostazioni del blocco.

---

## Struttura del progetto

```
moodle-reportcsv/
│
├── local/reportcsv/                  # Plugin principale
│   ├── index.php                     # Pannello admin
│   ├── ajax.php                      # Endpoint AJAX
│   ├── download.php                  # Serve i file CSV
│   ├── lib.php                       # Funzioni helper e update_env
│   ├── locallib.php                  # Stub di compatibilità
│   ├── settings.php                  # Impostazioni admin
│   ├── version.php
│   ├── classes/task/
│   │   └── generate_reports.php      # Scheduled task Moodle (fallback)
│   ├── db/
│   │   ├── access.php                # Capabilities
│   │   └── tasks.php                 # Definizione scheduled task
│   └── lang/
│       ├── en/local_reportcsv.php
│       └── it/local_reportcsv.php
│
├── blocks/reportcsv/                 # Blocco dashboard
│   ├── block_reportcsv.php           # Classe principale del blocco
│   ├── edit_form.php                 # Form configurazione (solo admin)
│   ├── version.php
│   ├── db/
│   │   ├── access.php                # Capabilities
│   │   └── upgrade.php
│   └── lang/
│       ├── en/block_reportcsv.php
│       └── it/block_reportcsv.php
│
└── moodle_export.sh                  # Script bash di export (da copiare in admin/cli/)
```

---

## Capabilities

| Capability | Descrizione | Default |
|---|---|---|
| `local/reportcsv:viewreports` | Vedere e scaricare i report | Tutti gli utenti autenticati |
| `local/reportcsv:managereports` | Gestire query, cron, impostazioni | Manager / Admin |
| `block/reportcsv:addinstance` | Aggiungere il blocco | Manager / Admin |

---

## Come funziona il file .env

Al salvataggio delle impostazioni, il plugin genera automaticamente un file `.env` in:

```
moodledata/reportcsv/.env
```

Questo file viene letto dallo script bash `moodle_export.sh` tramite la variabile d'ambiente `REPORTCSV_ENV_FILE`, che il plugin imposta automaticamente nei comandi cron generati.

Per i test manuali, passala esplicitamente:

```bash
REPORTCSV_ENV_FILE=/path/to/moodledata/reportcsv/.env ./moodle_export.sh query.sql
```

---

## Pubblicare su GitHub

### Prima configurazione

```bash
git init
git add .
git commit -m "Initial release v1.0.0"
git branch -M main
git remote add origin https://github.com/TUO-USERNAME/moodle-reportcsv.git
git push -u origin main
```

### Creare una release

```bash
git tag -a v1.0.0 -m "Release 1.0.0"
git push origin v1.0.0
```

Poi su GitHub: **Releases → Create a new release**, seleziona il tag e allega i due zip.

---

## Licenza

[GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html) — compatibile con Moodle.

---

## Contribuire

Pull request e issue sono benvenute. Per modifiche importanti apri prima una issue per discutere la direzione.

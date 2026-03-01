# OpenAI Classification Data Generation Instructions

Use this guide to prompt an AI assistant to generate realistic, label-balanced document classification datasets for Docintern.

## Goal

Generate synthetic but realistic labeled text samples for Docintern classes:

- `contract`
- `tax`
- `invoice`
- `general`

These datasets can be used for:

- offline quality evaluation,
- regression checks,
- optional supervised fine-tuning workflows.

## Required Output Files

- `openai_classification_train.csv`
- `openai_classification_test.csv`

CSV format (no header):

```csv
label,"text"
```

## Constraints

1. Use exactly these labels: `contract`, `tax`, `invoice`, `general`.
2. Generate balanced classes.
3. Include realistic language patterns from document types supported by the app (`pdf`, `doc`, `docx`, `xls`, `xlsx`, `jpg`, `jpeg`, `png`).
4. Avoid duplicates and near-duplicates.
5. Use synthetic entities only (no real confidential data).
6. Vary length and style (form-like, table-like, memo/email-like, clause-heavy legal text).

## Coverage Targets

- Train: `100` rows per label (`400` total)
- Test: `20` rows per label (`80` total)

## Copy/Paste Prompt For AI

```text
Generate two CSV datasets for legal document classification.

Output files:
1) openai_classification_train.csv
2) openai_classification_test.csv

Each row format (NO HEADER):
label,"text"

Allowed labels only:
contract, tax, invoice, general

Requirements:
- Train set: 100 rows per label (400 total)
- Test set: 20 rows per label (80 total)
- Balanced classes exactly.
- No duplicates or near-duplicates.
- Realistic legal/business language.
- Mix styles: contract clauses, tax form language, invoice line-item language, and general legal correspondence.
- Simulate language that could come from pdf/doc/docx/xls/xlsx/jpg/jpeg/png sources.
- Keep all data synthetic and non-sensitive.

Return:
1) fenced code block titled openai_classification_train.csv with only CSV lines
2) fenced code block titled openai_classification_test.csv with only CSV lines
3) class distribution summary
```

## Validation Commands

```bash
wc -l openai_classification_train.csv openai_classification_test.csv

cut -d',' -f1 openai_classification_train.csv | sort | uniq -c
cut -d',' -f1 openai_classification_test.csv | sort | uniq -c

cut -d',' -f1 openai_classification_train.csv | grep -Ev '^(contract|tax|invoice|general)$'
cut -d',' -f1 openai_classification_test.csv | grep -Ev '^(contract|tax|invoice|general)$'
```

## Runtime Configuration Reminder

Docintern live classification now uses OpenAI API keys and model selection:

```dotenv
OPENAI_API_KEY=REPLACE_WITH_OPENAI_API_KEY
OPENAI_BASE_URL=https://api.openai.com/v1
PROCESSING_OPENAI_MODEL=gpt-4o-mini
PROCESSING_OPENAI_TIMEOUT=15
```

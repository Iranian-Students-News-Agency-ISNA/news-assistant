# News Assistant 

[فارسی](./README.fa.md)

A small standalone page that lets a visitor ask about any topic ("what's the news on X?") and get a one-line direct answer plus the most relevant ISNA news items (sourced from the [isnabot](../isnabot) project's `news.json`), summarized on the fly.

Meant to be dropped into an existing site as a subfolder, e.g. `yourdomain.com/ai/news-assistant/`.

## How it works

1. The visitor types a question into the search box.
2. `api.php` locally keyword-filters today's cached ISNA news (from `isnabot`'s `news.json`) down to a short list, to keep the request small and fast.
3. That short list + the question are sent to an LLM (via [OpenRouter](https://openrouter.ai)) which returns:
   - a one-line direct answer to the question
   - up to 4 source news items with a one-line note on why each is relevant
4. The page shows the answer, then the source cards below it, plus a note on how fresh the underlying data is (ISNA cache resets daily at midnight, so results cover "since 00:00 today").

No vector database, no embeddings — just a lightweight keyword pre-filter + one LLM call. This keeps it deployable on plain shared/cPanel hosting with nothing but PHP + curl.

## Setup

1. Copy `config.example.php` to `config.php`:
   ```
   cp config.example.php config.php
   ```
2. Edit `config.php`:
   - `OPENROUTER_API_KEY` — get one free at [openrouter.ai](https://openrouter.ai/keys)
   - `OPENROUTER_MODEL` — defaults to `openrouter/free` (OpenRouter's free auto-router). You can point it at a paid model (e.g. `openai/gpt-4o`) for better quality.
   - `SITE_URL` / `SITE_NAME` — used in the `HTTP-Referer` / `X-Title` headers OpenRouter recommends sending.
   - `ISNABOT_NEWS_FILE` — path to the isnabot project's `data/news.json` on your server. Defaults to a sibling folder (`../../isnabot/data/news.json`), adjust if yours lives elsewhere.
3. Upload the folder to your host (e.g. as a subfolder of your main site).
4. `config.php` is git-ignored — never commit it, since it holds your API key.

## Requirements

- PHP with curl enabled
- Outbound HTTPS access to `openrouter.ai` from your host
- A running [isnabot](../isnabot) instance producing `news.json`

## Files

| File | Purpose |
|---|---|
| `index.php` | The page itself (search box, results UI, matches the parent site's theme) |
| `api.php` | Backend: keyword pre-filter + OpenRouter call + JSON response |
| `config.example.php` | Template config — copy to `config.php` and fill in your own values |

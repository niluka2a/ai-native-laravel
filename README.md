# Full-Stack Engineering Challenge

## Senior AI Native Full Stack Engineer (PHP and Next.js)

You are joining a team that uses AI while developing and maintaining a Laravel API and a
Next.js frontend. In this exercise, you will diagnose issues in an existing customer request
app and decide how to deliver a small feature. We want to see how you use AI to investigate
and implement changes, how you verify the results, and what you decide to ship.

## Timebox

Spend up to **4 hours** on the exercise after initial setup, including your tests and submission
notes. Prioritise tasks 1 to 4 and scope task 5. If you run out of time, record what is unfinished,
what you verified, and what you would do next in `DECISIONS.md`. Optional work can wait until you
have covered the priorities. Tell us if setup prevents you from starting.

## The starter app

- **API** uses Laravel 13, Eloquent and MariaDB in `apps/api`.
- **Web** uses Next.js, React, TypeScript, TanStack Query and Tailwind in `apps/web`.
- **CI** uses GitHub Actions in `.github/workflows/ci.yml`.

The app starts with seeded customer requests and notes. Work within the existing app and preserve
its behaviour unless a task calls for a change. Explain any deliberate behaviour changes.

## Quick start

With Docker and Docker Compose running, use the following from the repository root.

```bash
docker compose up --build
```

The first start installs dependencies, runs migrations and seeds the database. Open the
[web app](http://localhost:3000). The [requests API](http://localhost:3001/requests) listens on port 3001.

```bash
docker compose exec api php artisan test
docker compose exec api php artisan migrate
docker compose exec web npm run typecheck
docker compose logs -f api
```

The starter logs SQL queries in the API container. To stop the app, use `docker compose down`.
Add `-v` if you want to delete the local database and dependency volumes and start from scratch.

## Your tasks

1. **List performance.** `GET /requests` is slow with the seeded data. Improve how the API
   loads the list. Show a before and after check on the same data and confirm that note counts
   and the latest note preview stay correct.
2. **UI freshness.** Changing a request's status or classifying it succeeds in the API, but
   the table can show stale values. Make both actions update the table without a manual refresh.
3. **CI.** Diagnose the GitHub Actions failure on a clean checkout and keep the existing checks
   running. Link the workflow run if you can run Actions on your private copy. Otherwise,
   record which steps you reproduced and what you could not verify.
4. **Classification structure.** `POST /requests/classify` mixes input handling and business
   rules. Give validation and classification logic clear homes in Laravel, use types where they
   help, and add a regression test for behaviour you intend to preserve.
5. **Classification history.** Describe the smallest useful release in `DECISIONS.md`, including
   what you would store, when you would write it, and how staff would filter it. If time allows,
   ship a thin slice that records new classifications, serves history with a category filter
   through `GET /requests/history`, and makes `/history` useful. Include a provider interface so
   the existing keyword classifier can be swapped later. Keep the app runnable locally with
   the keyword classifier. A live model, API key and paid AI service are unnecessary.

If you have time after the priorities, you can explore a persistence boundary in one area of the
app, or explain deployment order and failure handling for a future model-backed classifier.

## How to work with AI

Use an AI coding assistant of your choice for a substantive part of the exercise, such as
diagnosing an issue, implementing a fix, or reviewing a change. You can use the free or paid
tools you already have access to. You own the code and the claims in your submission.

Keep brief checkpoints in `AI_USAGE.md` as you work. Capture two concrete decisions. For each,
record what you asked AI to do, the constraint you gave it, what it proposed, how you checked it
yourself, and whether you accepted, changed, or rejected it. Link to a file, commit, test, or
command and include the relevant result. A verified suggestion is a valid example. Write the
account of your own decisions yourself. AI can help edit it for clarity.

Short prompt excerpts or paraphrases are enough. Leave out secrets, personal data, and full
chat logs.

## Submission

Open a pull request in your private copy of the starter and share the link with the hiring team
using the access instructions in your invitation. Include the following.

- **PR summary** with what changed, how to run your checks, and the CI status.
- **`DECISIONS.md`** with your priorities, history scope, assumptions, and unfinished work.
- **`AI_USAGE.md`** with the tools you used and your two decision checkpoints in working order.
- **Tests** for the behaviour you changed. Explain any verification you ran manually.

In the walkthrough, expect to explain a change, reproduce a check you used to verify AI output,
and discuss how you would respond to a small change in the requirements. We assess correctness,
maintainability, prioritisation, and how well you direct and verify AI-assisted work. Tool choice,
paid subscriptions, commit count, and optional tasks carry no penalty.

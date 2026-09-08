# Question privacy rollout

## Access policy

Only public questions in open, answered or closed status appear in the public list and search. Private, paid, moderation and archived questions can be read only by their author. Other callers receive 404 before content or view counters are touched. The lawyer role alone does not grant access or permission to answer a restricted question: participant assignment is not implemented yet.

## Deployment sequence

1. Merge the build-fix PR first, then this PR. Take the normal database and search snapshots before rollout.
2. Deploy this version to all API instances and restart Messenger workers with the same version. Old workers must not continue writing documents without visibility metadata. Until every API instance is updated, old instances retain the original exposure.
3. In the backend runtime, run `APP_ENV=prod php bin/console app:search-reindex --no-interaction` and verify exit code 0. This adds the question type mapping, removes private/hidden and legacy question documents without visibility metadata, then rebuilds the searchable public records. Database questions are not deleted. If interrupted, fix the error and rerun the command.
4. Verify a known private/paid question returns 404 without its author's token and does not appear in `/api/v1/search?q=...` or `/api/v1/search?q=...&type=questions`; verify its author can still read it. Check that a public question appears after the search refresh.

The search filter fails closed for legacy question documents lacking type metadata, so public question search may temporarily be incomplete until reindexing finishes. Keep OpenSearch accessible only to trusted backend services; API filters do not protect direct index access or historical backups. Do not roll back to the vulnerable API/worker code after cleanup.

Future type/status edit flows must update or delete the index document when visibility changes. This patch does not implement lawyer assignment or a paid consultation workflow. Existing full reindexing still loads entities with findAll; batching is a separate scaling task.

## Regression coverage

Run `cd backend && vendor/bin/phpunit`. HTTP tests use an isolated temporary SQLite database and ephemeral RSA keys, requiring pdo_sqlite and openssl. They exercise real routing, JWT authentication, ORM loading, list filters and answer permissions. OpenSearch client tests cover publication metadata, visibility filters and stale document deletion; a live OpenSearch cluster is not used by this suite.

# Database deployment

## Runtime DDL migration

Backoffice requests must not create or alter database objects. Before deploying
the PHP files that remove Runtime DDL, run the migration with a database account
that temporarily has `SELECT`, `CREATE`, `ALTER`, and `INDEX` privileges:

```bash
php database/migrations/20260923_001_remove_runtime_ddl.php
```

The migration checks `information_schema` before adding a column and is safe to
run more than once. A successful repeat run ends with `0 column changes`.

Deployment order:

1. Back up the production database.
2. Put the application into a maintenance window if schema changes are pending.
3. Run the migration using the migration/DBA account.
4. Deploy the PHP files.
5. Configure the application to use the restricted runtime account.
6. Test login, read, add, edit, soft delete, sort, and upload operations.
7. Remove temporary migration privileges.

## Runtime account

The web application needs only `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on its
application schema after migrations are complete. It must not have global
privileges or `CREATE`, `ALTER`, `DROP`, `CREATE USER`, `GRANT`, `FILE`, or
`SUPER` privileges.

Use `least_privilege.sql.example` as a DBA-reviewed template. Replace every
placeholder before running it. Do not place a real password in a committed SQL
file; put the resulting runtime credentials in the production `.env` only.

The current Docker development account has global administrative privileges.
It is intentionally unchanged so local development is not interrupted.

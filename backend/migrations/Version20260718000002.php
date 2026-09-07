<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** V2: Payment Context (LiqPay) + LegalBase Context с Postgres FTS. */
final class Version20260718000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V2: payments/subscriptions (LiqPay) + law_documents/court_decisions with FTS';
    }

    public function up(Schema $schema): void
    {
        // ═══ Payment Context ═══
        $this->addSql(<<<'SQL'
            CREATE TABLE subscription_plans (
                id UUID PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                price NUMERIC(10,2) NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'UAH',
                interval VARCHAR(10) NOT NULL DEFAULT 'month',
                features JSONB NOT NULL DEFAULT '[]',
                is_active BOOLEAN NOT NULL DEFAULT TRUE
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE subscriptions (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                plan_id UUID NOT NULL REFERENCES subscription_plans (id),
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                auto_renew BOOLEAN NOT NULL DEFAULT FALSE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_subscriptions_user_status ON subscriptions (user_id, status)');

        $this->addSql(<<<'SQL'
            CREATE TABLE payments (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                order_id VARCHAR(64) NOT NULL UNIQUE,
                amount NUMERIC(10,2) NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'UAH',
                type VARCHAR(30) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                gateway VARCHAR(30) NOT NULL DEFAULT 'liqpay',
                gateway_tx_id VARCHAR(100) DEFAULT NULL,
                metadata JSONB NOT NULL DEFAULT '{}',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_payments_user_status ON payments (user_id, status, created_at)');
        $this->addSql('CREATE INDEX idx_payments_gateway_tx ON payments (gateway, gateway_tx_id)');

        // ═══ LegalBase Context ═══
        $this->addSql(<<<'SQL'
            CREATE TABLE law_documents (
                id UUID PRIMARY KEY,
                type VARCHAR(30) NOT NULL,
                number VARCHAR(100) NOT NULL,
                slug VARCHAR(300) NOT NULL UNIQUE,
                title VARCHAR(500) NOT NULL,
                body TEXT NOT NULL,
                issued_by VARCHAR(300) NOT NULL,
                issued_at DATE NOT NULL,
                effective_from DATE DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                version INT NOT NULL DEFAULT 1,
                parent_id UUID DEFAULT NULL REFERENCES law_documents (id) ON DELETE SET NULL,
                views_count INT NOT NULL DEFAULT 0,
                is_pro_only BOOLEAN NOT NULL DEFAULT FALSE,
                search_vector TSVECTOR,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_law_type_status ON law_documents (type, status)');
        // GIN-индекс для FTS (ТЗ §10.2 idx_law_fts)
        $this->addSql('CREATE INDEX idx_law_fts ON law_documents USING GIN (search_vector)');

        // Триггер: search_vector = title (вес A) + body (вес B); словарь simple —
        // работает для любого языка без стемминга (украинского словаря в PG нет из коробки)
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION law_documents_tsv_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector :=
                    setweight(to_tsvector('simple', coalesce(NEW.title, '')), 'A') ||
                    setweight(to_tsvector('simple', coalesce(NEW.body, '')), 'B');
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_law_documents_tsv
            BEFORE INSERT OR UPDATE OF title, body ON law_documents
            FOR EACH ROW EXECUTE FUNCTION law_documents_tsv_update()
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE courts (
                id UUID PRIMARY KEY,
                name VARCHAR(300) NOT NULL,
                type VARCHAR(30) NOT NULL,
                region VARCHAR(100) NOT NULL DEFAULT ''
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE court_decisions (
                id UUID PRIMARY KEY,
                court_id UUID NOT NULL REFERENCES courts (id),
                case_number VARCHAR(100) NOT NULL,
                title VARCHAR(500) NOT NULL,
                body TEXT NOT NULL,
                decided_at DATE NOT NULL,
                categories JSONB NOT NULL DEFAULT '[]',
                views_count INT NOT NULL DEFAULT 0,
                search_vector TSVECTOR,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_decisions_court_date ON court_decisions (court_id, decided_at DESC)');
        $this->addSql('CREATE INDEX idx_court_fts ON court_decisions USING GIN (search_vector)');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION court_decisions_tsv_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector :=
                    setweight(to_tsvector('simple', coalesce(NEW.title, '')), 'A') ||
                    setweight(to_tsvector('simple', coalesce(NEW.body, '')), 'B');
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_court_decisions_tsv
            BEFORE INSERT OR UPDATE OF title, body ON court_decisions
            FOR EACH ROW EXECUTE FUNCTION court_decisions_tsv_update()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_court_decisions_tsv ON court_decisions');
        $this->addSql('DROP TRIGGER IF EXISTS trg_law_documents_tsv ON law_documents');
        $this->addSql('DROP FUNCTION IF EXISTS court_decisions_tsv_update');
        $this->addSql('DROP FUNCTION IF EXISTS law_documents_tsv_update');
        foreach (['court_decisions', 'courts', 'law_documents', 'payments', 'subscriptions', 'subscription_plans'] as $t) {
            $this->addSql('DROP TABLE IF EXISTS ' . $t . ' CASCADE');
        }
    }
}

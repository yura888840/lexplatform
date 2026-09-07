<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V3 — Content Policy & Monetization:
 * компетенция через статьи (или оплата), документы верификации, согласия, комиссия 30%.
 */
final class Version20260718000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V3: lawyer compliance (articles-or-pay), verification docs, consents, payouts 30%';
    }

    public function up(Schema $schema): void
    {
        // ── Юрист: режим участия и контент-метрики ──
        $this->addSql(<<<'SQL'
            ALTER TABLE lawyer_profiles
                ADD contribution_mode VARCHAR(20) NOT NULL DEFAULT 'articles',
                ADD articles_published_count INT NOT NULL DEFAULT 0,
                ADD last_article_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                ADD compliance_status VARCHAR(20) NOT NULL DEFAULT 'onboarding',
                ADD content_score NUMERIC(3,2) NOT NULL DEFAULT 0
        SQL);
        $this->addSql('CREATE INDEX idx_lawyer_compliance ON lawyer_profiles (compliance_status)');

        // ── Статья: привязка к отрасли права, передача прав, объём ──
        $this->addSql(<<<'SQL'
            ALTER TABLE articles
                ADD specialization_id UUID DEFAULT NULL REFERENCES specializations (id) ON DELETE SET NULL,
                ADD copyright_transferred BOOLEAN NOT NULL DEFAULT FALSE,
                ADD chars_count INT NOT NULL DEFAULT 0
        SQL);
        $this->addSql('CREATE INDEX idx_articles_specialization ON articles (specialization_id, status)');

        // ── Документы верификации (свідоцтво адвоката / про освіту) ──
        $this->addSql(<<<'SQL'
            CREATE TABLE verification_documents (
                id UUID PRIMARY KEY,
                lawyer_id UUID NOT NULL REFERENCES lawyer_profiles (id) ON DELETE CASCADE,
                type VARCHAR(40) NOT NULL,
                file_url VARCHAR(700) NOT NULL,
                file_name VARCHAR(300) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                review_note TEXT DEFAULT NULL,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_verif_docs_lawyer ON verification_documents (lawyer_id, status)');

        // ── Согласия пользователей (персданные, передача авторских прав) ──
        $this->addSql(<<<'SQL'
            CREATE TABLE user_consents (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                type VARCHAR(40) NOT NULL,
                version VARCHAR(20) NOT NULL,
                accepted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                ip VARCHAR(45) DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_consents_user_type ON user_consents (user_id, type)');

        // ── Выплаты юристам (комиссия площадки 30%) ──
        $this->addSql(<<<'SQL'
            CREATE TABLE payouts (
                id UUID PRIMARY KEY,
                lawyer_id UUID NOT NULL REFERENCES lawyer_profiles (id) ON DELETE CASCADE,
                payment_id UUID NOT NULL UNIQUE REFERENCES payments (id) ON DELETE CASCADE,
                gross_amount NUMERIC(10,2) NOT NULL,
                commission_rate NUMERIC(4,3) NOT NULL,
                commission_amount NUMERIC(10,2) NOT NULL,
                net_amount NUMERIC(10,2) NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'UAH',
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_payouts_lawyer_status ON payouts (lawyer_id, status, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS payouts');
        $this->addSql('DROP TABLE IF EXISTS user_consents');
        $this->addSql('DROP TABLE IF EXISTS verification_documents');
        $this->addSql('ALTER TABLE articles DROP COLUMN IF EXISTS specialization_id, DROP COLUMN IF EXISTS copyright_transferred, DROP COLUMN IF EXISTS chars_count');
        $this->addSql('ALTER TABLE lawyer_profiles DROP COLUMN IF EXISTS contribution_mode, DROP COLUMN IF EXISTS articles_published_count, DROP COLUMN IF EXISTS last_article_at, DROP COLUMN IF EXISTS compliance_status, DROP COLUMN IF EXISTS content_score');
    }
}

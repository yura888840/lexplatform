<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** LexPlatform MVP: начальная схема (ТЗ §10.1, §10.2). */
final class Version20260718000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LexPlatform MVP initial schema: users, lawyers, Q&A, content, reviews';
    }

    public function up(Schema $schema): void
    {
        // ── users ──
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id UUID PRIMARY KEY,
                email VARCHAR(180) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                phone VARCHAR(32) DEFAULT NULL,
                avatar_url VARCHAR(500) DEFAULT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'client',
                status VARCHAR(30) NOT NULL DEFAULT 'pending_verification',
                email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                locale VARCHAR(5) NOT NULL DEFAULT 'uk',
                last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX idx_users_email ON users (email)');
        $this->addSql('CREATE INDEX idx_users_role_status ON users (role, status)');

        // ── refresh_tokens ──
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_refresh_user ON refresh_tokens (user_id)');

        // ── specializations ──
        $this->addSql(<<<'SQL'
            CREATE TABLE specializations (
                id UUID PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                parent_id UUID DEFAULT NULL REFERENCES specializations (id) ON DELETE SET NULL
            )
        SQL);

        // ── lawyer_profiles ──
        $this->addSql(<<<'SQL'
            CREATE TABLE lawyer_profiles (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL UNIQUE REFERENCES users (id) ON DELETE CASCADE,
                slug VARCHAR(255) NOT NULL UNIQUE,
                bar_number VARCHAR(100) DEFAULT NULL,
                bio TEXT NOT NULL DEFAULT '',
                experience_years INT NOT NULL DEFAULT 0,
                hourly_rate NUMERIC(10,2) DEFAULT NULL,
                rating NUMERIC(3,2) NOT NULL DEFAULT 0,
                reviews_count INT NOT NULL DEFAULT 0,
                consultations_count INT NOT NULL DEFAULT 0,
                response_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
                response_time_avg INT NOT NULL DEFAULT 0,
                city VARCHAR(100) NOT NULL DEFAULT '',
                region VARCHAR(100) NOT NULL DEFAULT '',
                is_online BOOLEAN NOT NULL DEFAULT FALSE,
                is_verified BOOLEAN NOT NULL DEFAULT FALSE,
                is_featured BOOLEAN NOT NULL DEFAULT FALSE,
                profile_completeness INT NOT NULL DEFAULT 0,
                video_consultation_enabled BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_lawyer_rating ON lawyer_profiles (rating DESC)');
        $this->addSql('CREATE INDEX idx_lawyer_featured ON lawyer_profiles (is_featured, rating DESC)');
        $this->addSql('CREATE INDEX idx_lawyer_city ON lawyer_profiles (city)');

        $this->addSql(<<<'SQL'
            CREATE TABLE lawyer_specializations (
                lawyer_id UUID NOT NULL REFERENCES lawyer_profiles (id) ON DELETE CASCADE,
                specialization_id UUID NOT NULL REFERENCES specializations (id) ON DELETE CASCADE,
                PRIMARY KEY (lawyer_id, specialization_id)
            )
        SQL);

        // ── categories ──
        $this->addSql(<<<'SQL'
            CREATE TABLE categories (
                id UUID PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                type VARCHAR(30) NOT NULL DEFAULT 'legal',
                parent_id UUID DEFAULT NULL REFERENCES categories (id) ON DELETE SET NULL,
                sort_order INT NOT NULL DEFAULT 0
            )
        SQL);

        // ── questions / answers ──
        $this->addSql(<<<'SQL'
            CREATE TABLE questions (
                id UUID PRIMARY KEY,
                author_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                title VARCHAR(300) NOT NULL,
                body TEXT NOT NULL,
                category_id UUID NOT NULL REFERENCES categories (id),
                type VARCHAR(20) NOT NULL DEFAULT 'public',
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                is_anonymous BOOLEAN NOT NULL DEFAULT FALSE,
                views_count INT NOT NULL DEFAULT 0,
                answers_count INT NOT NULL DEFAULT 0,
                accepted_answer_id UUID DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_questions_category_status ON questions (category_id, status, created_at DESC)');

        $this->addSql(<<<'SQL'
            CREATE TABLE answers (
                id UUID PRIMARY KEY,
                question_id UUID NOT NULL REFERENCES questions (id) ON DELETE CASCADE,
                author_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                body TEXT NOT NULL,
                is_accepted BOOLEAN NOT NULL DEFAULT FALSE,
                helpful_votes INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_answers_question ON answers (question_id, created_at)');

        // ── reviews ──
        $this->addSql(<<<'SQL'
            CREATE TABLE reviews (
                id UUID PRIMARY KEY,
                lawyer_id UUID NOT NULL REFERENCES lawyer_profiles (id) ON DELETE CASCADE,
                author_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                rating SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
                body TEXT DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_reviews_lawyer_status ON reviews (lawyer_id, status)');

        // ── articles ──
        $this->addSql(<<<'SQL'
            CREATE TABLE articles (
                id UUID PRIMARY KEY,
                type VARCHAR(30) NOT NULL DEFAULT 'article',
                title VARCHAR(300) NOT NULL,
                slug VARCHAR(300) NOT NULL UNIQUE,
                excerpt TEXT NOT NULL DEFAULT '',
                body TEXT NOT NULL,
                author_id UUID NOT NULL REFERENCES users (id),
                category_id UUID DEFAULT NULL REFERENCES categories (id),
                cover_image VARCHAR(500) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                views_count INT NOT NULL DEFAULT 0,
                seo_title VARCHAR(300) DEFAULT NULL,
                seo_description VARCHAR(500) DEFAULT NULL,
                published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_articles_published ON articles (status, published_at DESC)');
    }

    public function down(Schema $schema): void
    {
        foreach (['articles', 'reviews', 'answers', 'questions', 'categories', 'lawyer_specializations', 'lawyer_profiles', 'specializations', 'refresh_tokens', 'users'] as $table) {
            $this->addSql('DROP TABLE IF EXISTS ' . $table . ' CASCADE');
        }
    }
}

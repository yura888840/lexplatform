<?php
declare(strict_types=1);

namespace App\Application\Content\Command\SubmitLawyerArticle;

use App\Domain\Content\Entity\Article;
use App\Domain\Identity\Entity\User;
use App\Domain\Identity\Entity\UserConsent;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Lawyer\Entity\Specialization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Сабмит статьи юристом (бизнес-требование «підтвердження компетенції»):
 *  1. Автор — юрист с профилем;
 *  2. Отрасль права ∈ выбранных специализаций юриста («в ті галузі писали б статті»);
 *  3. Объём ≥ 2 страниц (по умолчанию 3600 знаков чистого текста);
 *  4. Обязательная передача прав порталу — фиксируется consent'ом с версией и IP.
 * Статья уходит в review — публикует редактор.
 */
final readonly class SubmitLawyerArticleHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%app.min_article_chars%')] private int $minArticleChars,
    ) {}

    public function __invoke(SubmitLawyerArticleCommand $command): Article
    {
        $author = $this->em->find(User::class, $command->authorId)
            ?? throw new \DomainException('Автор не найден.');

        if ($author->getRole() !== User::ROLE_LAWYER) {
            throw new \DomainException('Публиковать статьи в блог могут только юристы.');
        }

        $lawyer = $this->em->getRepository(LawyerProfile::class)->findOneBy(['user' => $author])
            ?? throw new \DomainException('Профиль юриста не найден.');

        $specialization = $this->em->find(Specialization::class, $command->specializationId)
            ?? throw new \DomainException('Отрасль права не найдена.');

        if (!$lawyer->getSpecializations()->contains($specialization)) {
            throw new \DomainException(sprintf(
                'Отрасль «%s» не входит в ваши специализации. Статьи пишутся только в выбранные при регистрации отрасли.',
                $specialization->getName()
            ));
        }

        $plainChars = mb_strlen(trim(strip_tags($command->body)));
        if ($plainChars < $this->minArticleChars) {
            throw new \DomainException(sprintf(
                'Минимальный объём статьи — %d знаков (~2 страницы). Сейчас: %d.',
                $this->minArticleChars, $plainChars
            ));
        }

        if (mb_strlen(trim($command->title)) < 15) {
            throw new \DomainException('Заголовок статьи слишком короткий (мин. 15 символов).');
        }

        if (!$command->acceptsCopyrightTransfer) {
            throw new \DomainException('Публикация возможна только при согласии на передачу прав на статью порталу.');
        }

        $slug = strtolower((new AsciiSlugger('uk'))->slug($command->title)->toString());
        if ($this->em->getRepository(Article::class)->findOneBy(['slug' => $slug])) {
            $slug .= '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        }

        $article = new Article($author, Article::TYPE_BLOG, trim($command->title), $slug, $command->body);
        $article->setSpecialization($specialization);
        $article->setCharsCount($plainChars);
        $article->setExcerpt(mb_substr(trim(strip_tags($command->body)), 0, 280) . '…');
        $article->transferCopyright();
        $article->markInReview();

        // Юридическая фиксация передачи прав
        $consent = new UserConsent(
            $author,
            UserConsent::TYPE_COPYRIGHT_TRANSFER,
            UserConsent::CURRENT_VERSION_COPYRIGHT,
            $command->ip,
        );

        $this->em->persist($article);
        $this->em->persist($consent);
        $this->em->flush();

        return $article;
    }
}

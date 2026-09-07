<?php
declare(strict_types=1);

namespace App\Application\Content\Event;

use App\Application\Shared\AsyncMessageInterface;
use Symfony\Component\Uid\Uuid;

/** Редактор опубликовал статью: обновить метрики юриста + индексация. */
final readonly class ArticlePublished implements AsyncMessageInterface
{
    public function __construct(public Uuid $articleId) {}
}

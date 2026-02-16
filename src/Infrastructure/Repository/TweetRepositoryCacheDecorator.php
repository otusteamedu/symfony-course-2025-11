<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Tweet;
use App\Domain\Model\TweetModel;
use App\Domain\Repository\TweetRepositoryInterface;
use App\Infrastructure\Storage\MetricsStorage;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class TweetRepositoryCacheDecorator implements TweetRepositoryInterface
{
    public function __construct(
        private TweetRepository $tweetRepository,
        private TagAwareCacheInterface $cache,
        private MetricsStorage $metricsStorage,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function create(Tweet $tweet): int
    {
        $result = $this->tweetRepository->create($tweet);
        $this->cache->invalidateTags([$this->getCacheTag()]);

        return $result;
    }

    /**
     * @return TweetModel[]
     * @throws InvalidArgumentException
     * @throws CacheException
     */
    public function getTweetsPaginated(int $page, int $perPage): array
    {
        $cacheKey = $this->getCacheKey($page, $perPage);
        $results = $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($page, $perPage) {
                $tweets = $this->tweetRepository->getTweetsPaginated($page, $perPage);
                $tweetModels = array_map(
                    static fn (Tweet $tweet): TweetModel => new TweetModel(
                        $tweet->getId(),
                        $tweet->getAuthor()->getLogin(),
                        $tweet->getAuthor()->getId(),
                        $tweet->getText(),
                        $tweet->getCreatedAt(),
                    ),
                    $tweets
                );
                $item->set($tweetModels);
                $item->tag($this->getCacheTag());

                return $tweetModels;
            }, null, $metadata
        );

        $metric = [] !== $metadata ? MetricsStorage::CACHE_HIT_PREFIX : MetricsStorage::CACHE_MISS_PREFIX;
        $this->metricsStorage->increment($metric . $cacheKey);

        return $results;
    }

    private function getCacheKey(int $page, int $perPage): string
    {
        return "tweets_{$page}_$perPage";
    }

    private function getCacheTag(): string
    {
        return 'tweets';
    }
}
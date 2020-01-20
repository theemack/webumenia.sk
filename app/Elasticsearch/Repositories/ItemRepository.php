<?php

namespace App\Elasticsearch\Repositories;

use App\Authority;
use App\Color;
use App\Filter\Contracts\Filter;
use App\Filter\Contracts\SearchRequest;
use App\IntegerRange;
use App\Item;
use App\SearchResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ItemRepository extends TranslatableRepository
{
    protected $modelClass = Item::class;

    protected $index = 'items';

    public function getSuggestions(int $size, string $search, string $locale = null): SearchResult
    {
        $response = $this->elasticsearch->search([
            'index' => $this->getLocalizedIndexName($locale),
            'size' => $size,
            'body' => [
                'query' => [
                    'multi_match' => [
                        'query' => $search,
                        'type' => 'cross_fields',
                        'fields' => ['identifier', 'title.suggest', 'author.suggest'],
                        'operator' => 'and',
                    ]
                ]
            ]
        ]);

        return $this->createSearchResult($response);
    }

    public function getSimilar(int $size, Model $model, $locale = null): SearchResult
    {
        $response = $this->elasticsearch->search([
            'index' => $this->getLocalizedIndexName($locale),
            'size' => $size,
            'body' => [
                'query' => [
                    'bool'=> [
                        'must' => [
                            [
                                'more_like_this' => [
                                    'like' => [
                                        [
                                            '_index' => $this->getLocalizedIndexName($locale),
                                            '_id' => $model->id,
                                        ]
                                    ],
                                    'fields' => [
                                        'author.folded',
                                        'title',
                                        'title.stemmed',
                                        'description.stemmed',
                                        'tag.folded',
                                        'place',
                                        'technique',
                                    ],
                                    'min_term_freq' => 1,
                                    'min_doc_freq' => 1,
                                    'minimum_should_match' => 1,
                                    'min_word_length' => 1,
                                ]
                            ]
                        ],
                        'should' => [
                            [
                                'term' => [
                                    'has_image' => [
                                        'value' => true,
                                        'boost' => 10,
                                    ]
                                ]
                            ],
                            [
                                'term' => ['has_iip' => true]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        return $this->createSearchResult($response);
    }

    protected function createBucketCollection(array $response, string $attribute): Collection
    {
        $choices = collect();
        foreach ($response['aggregations'][$attribute]['buckets'] as $bucket) {
            $key = $bucket['key'];

            if ($attribute === 'author') {
                $key = formatName($key);
            }

            $label = sprintf('%s (%d)', $key, $bucket['doc_count']);
            $choices[$label] = $bucket['key'];
        }

        return $choices;
    }

    public function getPreviewItems(int $size, Authority $authority, string $locale = null): Collection
    {
        $response = $this->elasticsearch->search([
            'index' => $this->getLocalizedIndexName($locale),
            'body' => [
                'size' => $size,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['authority_id' => $authority->id]],
                        ],
                        'should' => [
                            ['term' => ['has_image' => true]],
                            ['term' => ['has_iip' => true]],
                        ],
                    ],
                ],
                'sort' => [
                    '_score',
                    ['created_at' => ['order' => 'asc']],
                ]
            ],
        ]);

        return $this->createSearchResult($response)->getCollection();
    }

    public function buildQueryFromFilter(?Filter $filter): ?array
    {
        if (!$filter) {
            return null;
        }

        $query = [];
        $query = $this->addFilterablesQuery($query, $filter);
        $query = $this->addSearchQuery($query, $filter->get('search'));
        $query = $this->addYearsQuery($query, $filter->get('years'));
        $query = $this->addColorQuery($query, $filter->get('color'));
        return $query ?: null;
    }

    protected function addSearchQuery(array $query, ?string $search): array
    {
        if ($search === null) {
            return $query;
        }

        $should_match = [
            'identifier' => [
                'query' => $search,
                'boost' => 10,
            ],
            'author.folded' => [
                'query' => $search,
                'boost' => 5,
            ],
            'title' => $search,
            'title.folded' => $search,
            'title.stemmed' => [
                'query' => $search,
                'analyzer' => 'synonyms_analyzer'
            ],
            'tag.folded' => $search,
            'tag.stemmed' => $search,
            'place.folded' => $search,
            'description' => $search,
            'description.stemmed' => [
                'query' => $search,
                'analyzer' => 'synonyms_analyzer',
                'boost' => 0.5,
            ],
        ];

        $should = [];
        foreach ($should_match as $key => $match) {
            $should[] = ['match' => [$key => $match]];
        }

        $query['bool']['should'] = $should;
        $query['bool']['minimum_should_match'] = 1;
        return $query;
    }

    protected function addYearsQuery(array $query, ?IntegerRange $years): array
    {
        if (!$years) {
            return $query;
        }

        if ($years->getFrom() !== null) {
            $query['bool']['filter'][]['range']['date_latest']['gte'] = $years->getFrom();
        }

        if ($years->getTo() !== null) {
            $query['bool']['filter'][]['range']['date_earliest']['lte'] = $years->getTo();
        }

        return $query;
    }

    protected function addColorQuery(array $query, ?Color $color): array
    {
        if (!$color) {
            return $query;
        }

        $query['bool']['should'][]['descriptor'] = [
            'color_descriptor' => [
                'hash' => 'LSH',
                'descriptor' => $color->getDescriptor(),
            ]
        ];

        return $query;
    }

    protected function addSort(array $body, ?string $sortBy): array
    {
        $sort = [];
        if ($sortBy === null) {
            $sort[] = '_score';
            $sort[] = ['has_image' => ['order' => 'desc']];
            $sort[] = ['has_iip' => ['order' => 'desc']];
            $sort[] = ['updated_at' => ['order' => 'desc']];
            $sort[] = ['created_at' => ['order' => 'desc']];
        } else {
            $sortBy = in_array($sortBy, ['newest', 'oldest']) ? 'date_earliest' : $sortBy;
            $sortOrder = in_array($sortBy, ['author', 'title', 'oldest']) ? 'asc' : 'desc';
            $sort[] = [$sortBy => ['order' => $sortOrder]];
        }

        $body['sort'] = $sort;
        return $body;
    }

    protected function getIndexConfig(string $locale  = null): array
    {
        return config('elasticsearch.index.items')[$this->getLocale($locale)];
    }

    protected function getMappingConfig(string $locale = null): array
    {
        return config('elasticsearch.mapping.items')[$this->getLocale($locale)];
    }
}
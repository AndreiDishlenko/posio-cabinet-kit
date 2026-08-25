<?php

namespace Posio\CabinetKit\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Posio\CabinetKit\Models\SeoMeta;

/**
 * Хранилище SEO-записей. Мягко удалённые строки отдаются вместе с остальными и
 * помечаются плоским признаком: таблица кабинета сама решает, показывать их или
 * нет, и по нему же переключает пункты «Удалить» / «Восстановить».
 *
 * В исходном проекте это наследник общего репозитория с привязкой к аккаунту.
 * Здесь запись глобальная (SEO принадлежит сайту, а не аккаунту), поэтому набор
 * операций короткий и живёт прямо в классе.
 */
class SeoRepository
{
    protected array $publicFields = [
        'id',
        'route_name',
        'page_name',
        'locale',
        'index',
        'canonical_url',
        'meta_title',
        'meta_keywords',
        'meta_description',
        'changeFrequency',
        'priority',
        'is_published',
        'is_deleted',
        // Open Graph
        'og_image',
        'og_title',
        'og_description',
        // Twitter Card
        'twitter_image',
        'twitter_title',
        'twitter_description',
        // JSON-LD
        'jsonld_add_software',
        'jsonld_add_organization',
    ];

    public function getAll(): Collection
    {
        return SeoMeta::query()
            ->select(['*', DB::raw('if(deleted_at is not null, true, false) as is_deleted')])
            ->withTrashed()
            ->orderByRaw('route_name')
            ->get()
            ->map(fn ($entity) => collect($entity->toArray())->only($this->publicFields));
    }

    public function create(array $data): Model
    {
        return SeoMeta::create(collect($data)->except(['id'])->toArray());
    }

    public function update(int $id, array $data)
    {
        return SeoMeta::where('id', $id)->update(collect($data)->except(['id'])->toArray());
    }

    public function deleteEntity(int $id)
    {
        return SeoMeta::where('id', $id)->delete();
    }

    public function restoreEntity(int $id)
    {
        return SeoMeta::where('id', $id)->withTrashed()->restore();
    }
}

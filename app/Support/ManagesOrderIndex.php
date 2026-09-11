<?php

namespace App\Support;

/** Existing API retained, with bounded updates instead of a write for every row. */
trait ManagesOrderIndex
{
    use \CMS\SiteManager\Support\ManagesOrderIndex {
        normalizeOrderIndex as private legacyNormalizeOrderIndex;
    }

    protected function normalizeOrderIndex(string $modelClass): void
    {
        $position = 0;
        $modelClass::orderBy('order_index')->orderBy('id')->chunk(500, function ($items) use ($modelClass, &$position) {
            foreach ($items as $item) {
                $position++;
                if ((int) $item->order_index !== $position) $modelClass::whereKey($item->id)->update(['order_index' => $position]);
            }
        });
    }

    protected function moveOrder($item, int $order): void
    {
        $class = get_class($item);
        $newOrder = $this->resolveOrderForReorder($class, $order);
        $oldOrder = (int) $item->order_index;
        if ($newOrder > $oldOrder) $class::where('order_index', '>', $oldOrder)->where('order_index', '<=', $newOrder)->decrement('order_index');
        if ($newOrder < $oldOrder) $class::where('order_index', '>=', $newOrder)->where('order_index', '<', $oldOrder)->increment('order_index');
        $item->update(['order_index' => $newOrder]);
    }
}

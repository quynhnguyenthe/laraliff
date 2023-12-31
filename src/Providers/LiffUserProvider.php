<?php

namespace QuynhNguyenThe\Laraliff\Providers;

use Illuminate\Auth\EloquentUserProvider;

class LiffUserProvider extends EloquentUserProvider
{
    public function retrieveByLiffId($id)
    {
        return $this->createModel()->newQuery()
            ->where(config('laraliff.fields.liff_id'), $id)
            ->first();
    }
}

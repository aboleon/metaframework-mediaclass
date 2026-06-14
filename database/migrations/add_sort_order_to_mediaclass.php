<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mediaclass') || Schema::hasColumn('mediaclass', 'sort_order')) {
            return;
        }

        Schema::table('mediaclass', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->index();
        });

        $scope = null;
        $sortOrder = 0;
        $updates = [];

        DB::table('mediaclass')
            ->select(['id', 'model_type', 'model_id', 'group'])
            ->orderBy('model_type')
            ->orderBy('model_id')
            ->orderBy('group')
            ->orderByRaw(
                "CASE position
                    WHEN 'left' THEN 1
                    WHEN 'right' THEN 2
                    WHEN 'up' THEN 3
                    WHEN 'down' THEN 4
                    ELSE 5
                END",
            )
            ->orderBy('id')
            ->get()
            ->each(function (object $media) use (&$scope, &$sortOrder, &$updates): void {
                $mediaScope = [
                    (string) $media->model_type,
                    $media->model_id === null ? null : (int) $media->model_id,
                    (string) $media->group,
                ];

                if ($mediaScope !== $scope) {
                    $scope = $mediaScope;
                    $sortOrder = 0;
                }

                $updates[(int) $media->id] = ++$sortOrder;
            });

        collect($updates)
            ->chunk(500)
            ->each(function ($chunk): void {
                $sortOrderCase = $chunk
                    ->map(
                        fn (int $sortOrder, int $mediaId): string => sprintf(
                            'WHEN %d THEN %d',
                            $mediaId,
                            $sortOrder,
                        ),
                    )
                    ->implode(' ');

                DB::table('mediaclass')
                    ->whereIn('id', $chunk->keys())
                    ->update([
                        'sort_order' => DB::raw("CASE id {$sortOrderCase} END"),
                    ]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mediaclass') || !Schema::hasColumn('mediaclass', 'sort_order')) {
            return;
        }

        Schema::table('mediaclass', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};

<?php

namespace App\Nova;

use App\Api\FakeApi;
use App\Models\Subscription as SubscriptionModel;
use App\Models\SubscriptionCategory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

class Subscription extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Subscription::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $categoryPresentationFields = [];

        if ($this->site_id) {
            $categories = FakeApi::getCategoryNames($this->site_id);

            $categoryPresentationFields[] = Text::make('ID', fn () => 'Name')
                ->onlyOnDetail();
            
            foreach ($categories as $categoryId => $categoryName) {
                if ($this->subscriptionCategories->contains('category_id', $categoryId)) {
                    $categoryPresentationFields[] = Text::make($categoryId, fn () => $categoryName)
                        ->onlyOnDetail()
                        ->copyable();
                }
            }
        }

        return [
            ID::make()->sortable(),

            BelongsTo::make('User'),

            Select::make('Site', 'site_id')
                ->options(FakeApi::getSiteNames())
                ->displayUsingLabels()
                ->searchable()
                ->rules(['required']),

            (new Panel('Categories', [
                BooleanGroup::make('Categories', 'subscription_categories')
                    ->dependsOn(
                        ['site_id'],
                        function (BooleanGroup $field, NovaRequest $request, FormData $formData) {
                            // The filled resource is only available on the initial request.
                            // It is not made available on dependsOn request, so you have to query
                            // the database to retrieve the model if the resource is an empty model
                            // (that's the case if no attributes are set).
                            $subscription = $this->resource?->getAttributes()
                                ? $this
                                : SubscriptionModel::find($request->resourceId);

                            $categories = FakeApi::getCategoryNames($formData->site_id);

                            $subscriptionCategories = $subscription?->subscriptionCategories ?? collect();
    
                            $selectedCategories = $categories
                                ->mapWithKeys(
                                    function ($category, $categoryKey)
                                    use ($formData, $subscription, $subscriptionCategories) {
                                        return [
                                            $categoryKey => (
                                                $subscription?->site_id === $formData->site_id
                                                && $subscriptionCategories->contains('category_id', $categoryKey)
                                            )
                                        ];
                                    }
                                )
                                ->toArray();

                            $field->options($categories)
                                ->withMeta(['value' => $selectedCategories]);
                        }
                    )
                    ->fillUsing(function ($request) {
                        $selectedCategories = collect(json_decode($request->subscription_categories, true))
                            ->filter(fn ($active) => $active)
                            ->keys()
                            ->toArray();
        
                        $request->merge(['selected_subscription_categories' => $selectedCategories]);
                    })
                    // If you comment resolveUsing, the dependsOn logic works (reactive).
                    // ->resolveUsing(function ($category, $subscription) {
                    //     // Since resolveUsing is called everytime dependsOn gets triggered,
                    //     // I had to check request()->site_id first because when creating, there's no model instance set.
                    //     // Even if the $siteId contains the appropriate value
                    //     // and $options are assigned properly, the mere fact that a resolveUsing callback is defined
                    //     // seems to completely render the dependant behavior unreactive.
                    //     $siteId = request()->site_id ?: $subscription->site_id;

                    //     $categories = FakeApi::getCategoryNames($siteId);

                    //     $subscriptionCategories = $subscription->subscriptionCategories;

                    //     return $categories
                    //         ->mapWithKeys(
                    //             function ($category, $categoryKey)
                    //             use ($subscription, $siteId, $subscriptionCategories) {
                    //                 return [
                    //                     $categoryKey => (
                    //                         $siteId === $subscription->site_id
                    //                         && $subscriptionCategories->contains('category_id', $categoryKey)
                    //                     )
                    //                 ];
                    //             }
                    //         )
                    //         ->toArray();
                    // })
                    ->onlyOnForms(),
                
                ...$categoryPresentationFields,
            ]))
                ->collapsable(),
        ];
    }

    /**
     * Register a callback to be called after the resource is created.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $subscription
     * @return void
     */
    public static function afterCreate(NovaRequest $request, Model $subscription)
    {
        static::syncCategories($subscription, $request->selected_subscription_categories);
    }

    /**
     * Register a callback to be called after the resource is updated.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $subscription
     * @return void
     */
    public static function afterUpdate(NovaRequest $request, Model $subscription)
    {
        static::syncCategories($subscription, $request->selected_subscription_categories);
    }

    /**
     * Sync the subscription categories.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\App\Models\Subscription  $subscription
     * @param  array  $selectedCategories
     * @return void
     */
    public static function syncCategories(Model|SubscriptionModel $subscription, array $selectedCategories): void
    {
        $subscription->subscriptionCategories()
            ->whereNotIn('category_id', $selectedCategories)
            ->forceDelete();

        $subscriptionCategories = $subscription->subscriptionCategories
            ->toArray();

        foreach ($selectedCategories as $selectedCategory) {
            if (! in_array($selectedCategory, $subscriptionCategories)) {
                SubscriptionCategory::create([
                    'category_id' => $selectedCategory,
                    'subscription_id' => $subscription->id,
                ]);
            }
        }
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}

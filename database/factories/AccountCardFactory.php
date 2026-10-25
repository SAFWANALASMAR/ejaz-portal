<?php

namespace Database\Factories;

use App\Enums\AccountCardAudience;
use App\Enums\SiteIcon;
use App\Models\AccountCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountCard>
 */
class AccountCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => SiteIcon::Link,
            'route' => null,
            'url' => 'https://'.fake()->domainName(),
            'audience' => AccountCardAudience::Everyone,
            'sort' => 100,
            'is_visible' => true,
        ];
    }
}

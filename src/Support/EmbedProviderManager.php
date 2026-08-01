<?php

declare(strict_types=1);

namespace MetaFramework\Mediaclass\Support;

use Illuminate\Contracts\Container\Container;
use MetaFramework\Mediaclass\Contracts\EmbedProvider;
use MetaFramework\Mediaclass\Data\ExternalVideoEmbed;
use MetaFramework\Mediaclass\VideoEmbedders\Tf1InfoEmbedProvider;
use Throwable;

class EmbedProviderManager
{
    /** @var array<int, EmbedProvider|class-string<EmbedProvider>> */
    private array $providers = [];

    /** @param  iterable<EmbedProvider|class-string<EmbedProvider>>  $providers */
    public function __construct(
        private readonly ?Container $container = null,
        iterable $providers = [],
    ) {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    public static function withDefaults(): self
    {
        return new self(providers: [
            new Tf1InfoEmbedProvider,
        ]);
    }

    /** @param  EmbedProvider|class-string<EmbedProvider>  $provider */
    public function register(EmbedProvider|string $provider): self
    {
        if ($this->has($provider)) {
            return $this;
        }

        $this->providers[] = $provider;

        return $this;
    }

    public function embed(string $url): ?ExternalVideoEmbed
    {
        foreach ($this->providers as $registeredProvider) {
            try {
                $provider = $this->resolve($registeredProvider);

                if (!$provider->supports($url)) {
                    continue;
                }

                $embed = $provider->embed($url);
            } catch (Throwable) {
                continue;
            }

            if ($embed instanceof ExternalVideoEmbed) {
                return $embed;
            }
        }

        return null;
    }

    /** @param  EmbedProvider|class-string<EmbedProvider>  $provider */
    private function has(EmbedProvider|string $provider): bool
    {
        $class = is_string($provider) ? $provider : $provider::class;

        return collect($this->providers)
            ->contains(fn (EmbedProvider|string $registered): bool => (is_string($registered) ? $registered : $registered::class) === $class);
    }

    /** @param  EmbedProvider|class-string<EmbedProvider>  $provider */
    private function resolve(EmbedProvider|string $provider): EmbedProvider
    {
        if ($provider instanceof EmbedProvider) {
            return $provider;
        }

        return $this->container?->make($provider) ?? new $provider;
    }
}

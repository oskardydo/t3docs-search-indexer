<?php

namespace App\Twig;

use App\Config\Labels;
use App\Dto\SearchDemand;
use App\Helper\VersionFilter;
use App\Helper\VersionSorter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly Environment $twigEnvironment,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_assets', $this->renderAssets(...)),
            new TwigFunction('render_single_asset', $this->renderSingleAsset(...)),
            new TwigFunction('aggregationBucket', $this->aggregationBucket(...), ['is_safe' => ['html']]),
            new TwigFunction('generateLinkWithout', $this->generateLinkWithout(...)),
            new TwigFunction('generateLinkWith', $this->generateLinkWith(...)),
            new TwigFunction('getLabelForFilter', $this->getLabelForFilter(...)),
            new TwigFunction('sortVersions', VersionSorter::sortVersions(...)),
            new TwigFunction('filterVersions', VersionFilter::filterVersions(...)),
        ];
    }

    public function renderAssets(string $assetType, string $assetLocation = 'header'): string
    {
        $assets = $this->getAssetByTypeAndLocation($assetType, $assetLocation);

        return $this->twigEnvironment->render('extension/assets.html.twig', [
            'assets' => $assets,
            'assetType' => $assetType,
        ]);
    }

    public function renderSingleAsset(string $assetUrl, string $assetType): string
    {
        $isUrlExternal = filter_var($assetUrl, FILTER_VALIDATE_URL);
        $isLocalAsset = $isUrlExternal ? true : false;

        return $this->twigEnvironment->render('extension/single_assert.html.twig', [
            'assetUrl' => $assetUrl,
            'assetType' => $assetType,
            'isLocalAsset' => $isLocalAsset,
        ]);
    }

    private function getAssetByTypeAndLocation(string $assetType, string $assetLocation): array
    {
        $assetsConfig = $this->parameterBag->get('assets');

        return $assetsConfig[$assetType][$assetLocation] ?? [];
    }

    public function aggregationBucket(string $category, string $index, array $bucket): string
    {
        $category = strtolower($category);
        $label = $bucket['key_as_string'] ?? $bucket['key'];
        $docCount = $bucket['doc_count'];
        $key = $bucket['key'];

        // check if checkbox has been set
        if (isset($_GET['filters'][$category][$key]) && $_GET['filters'][$category][$key] === 'true') {
            $checked = ' checked';
        } else {
            $checked = '';
        }
        return '<div class="form-check">'
            . '<input type="checkbox" class="form-check-input" id="' . $category . '-' . $index . '" name="filters[' . $category . '][' . $key . ']" ' . $checked . ' value="true" onchange="this.form.submit()">'
            . '<label class="form-check-label custom-control-label-hascount" for="' . $category . '-' . $index . '">'
            . '<span class="custom-control-label-title">' . $label . '</span> <span class="custom-control-label-count">(' . $docCount . ')</span>'
            . '</label>'
            . '</div>';
    }

    /**
     * @param      $in
     *
     * @return array|string|string[]
     */
    private function fixWording($in, bool $lowerCase = true)
    {
        $in = str_replace(' ', '_', (string)$in);
        if ($lowerCase) {
            $in = strtolower($in);
        }

        return $in;
    }

    private function generateLinkWithout(SearchDemand $demand, string $key, mixed $value, bool $removeQuery = true): string
    {
        $filters['filters'] = $demand->withFilterValueForLinkGeneration($key, $value);

        if ($removeQuery === false) {
            $filters['q'] = $demand->getQuery();
        }

        return $this->urlGenerator->generate('search-with-suggest', $filters);
    }

    private function generateLinkWith(SearchDemand $demand, string $key, mixed $value, bool $removeQuery = true): string
    {
        $filters['filters'] = $demand->withoutFilterValueForLinkGeneration($key, $value);

        if ($removeQuery === false) {
            $filters['q'] = $demand->getQuery();
        }

        return $this->urlGenerator->generate('search-with-suggest', $filters);
    }

    private function getLabelForFilter(string $filter): string
    {
        return Labels::getLabelForEsColumn($filter);
    }
}

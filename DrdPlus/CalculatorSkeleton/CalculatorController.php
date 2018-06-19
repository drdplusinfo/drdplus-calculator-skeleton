<?php
namespace DrdPlus\CalculatorSkeleton;

use DrdPlus\FrontendSkeleton\HtmlHelper;

class CalculatorController extends \DrdPlus\FrontendSkeleton\FrontendController
{

    public const DELETE_HISTORY = 'delete_history';
    public const REMEMBER_CURRENT = 'remember_current';

    /** @var string */
    private $sourceCodeUrl;
    /** @var Memory */
    private $memory;
    /** @var CurrentValues */
    private $currentValues;
    /** @var History */
    private $history;

    /**
     * @param HtmlHelper $htmlHelper
     * @param string $sourceCodeUrl
     * @param string $cookiesPostfix
     * @param string $documentRoot
     * @param string $vendorRoot
     * @param string|null $partsRoot
     * @param string|null $genericPartsRoot
     * @param int|null $cookiesTtl
     * @param array|null $selectedValues
     * @throws \DrdPlus\CalculatorSkeleton\Exceptions\SourceCodeUrlIsNotValid
     */
    public function __construct(
        HtmlHelper $htmlHelper,
        string $sourceCodeUrl,
        string $cookiesPostfix,
        string $documentRoot,
        string $vendorRoot,
        string $partsRoot = null,
        string $genericPartsRoot = null,
        int $cookiesTtl = null,
        array $selectedValues = null
    )
    {
        parent::__construct(
            $htmlHelper,
            $documentRoot,
            null, // web root detected automatically
            $vendorRoot,
            $partsRoot ?? \file_exists($documentRoot . '/parts') // parts root
                ? ($documentRoot . '/parts')
                : ($vendorRoot . '/drd-plus/calculator-skeleton/parts'),
            $genericPartsRoot ?? __DIR__ . '/../../parts/calculator-skeleton' // generic parts root
        );
        if (!\filter_var($sourceCodeUrl, \FILTER_VALIDATE_URL)) {
            throw new Exceptions\SourceCodeUrlIsNotValid("Given source code URL is not a valid one: '{$sourceCodeUrl}'");
        }
        $this->sourceCodeUrl = $sourceCodeUrl;
        $selectedValues = $selectedValues ?? $_GET;
        $this->memory = $this->createMemory($selectedValues /* as values to remember */, $cookiesPostfix, $cookiesTtl);
        $this->currentValues = $this->createCurrentValues($selectedValues, $this->getMemory());
        $this->history = $this->createHistory($selectedValues, $cookiesPostfix, $cookiesTtl);
    }

    protected function createMemory(array $values, string $cookiesPostfix, int $cookiesTtl = null): Memory
    {
        return new Memory(
            !empty($_POST[self::DELETE_HISTORY]),
            $values,
            !empty($values[self::REMEMBER_CURRENT]),
            $cookiesPostfix,
            $cookiesTtl
        );
    }

    protected function createCurrentValues(array $selectedValues, Memory $memory): CurrentValues
    {
        return new CurrentValues($selectedValues, $memory);
    }

    protected function createHistory(array $values, string $cookiesPostfix, int $cookiesTtl = null): History
    {
        return new History(
            !empty($_POST[self::DELETE_HISTORY]),
            $values,
            !empty($values[self::REMEMBER_CURRENT]),
            $cookiesPostfix,
            $cookiesTtl
        );
    }

    /**
     * @return string
     */
    public function getSourceCodeUrl(): string
    {
        return $this->sourceCodeUrl;
    }

    /**
     * @return Memory
     */
    protected function getMemory(): Memory
    {
        return $this->memory;
    }

    /**
     * @return History
     */
    protected function getHistory(): History
    {
        return $this->history;
    }

    /**
     * @return CurrentValues
     */
    public function getCurrentValues(): CurrentValues
    {
        return $this->currentValues;
    }

    /**
     * Its almost same as @see getBagEnds, but gives in a flat array BOTH array values AND indexes from given array
     *
     * @param array $values
     * @return array
     */
    protected function toFlatArray(array $values): array
    {
        $flat = [];
        foreach ($values as $index => $value) {
            if (\is_array($value)) {
                $flat[] = $index;
                foreach ($this->toFlatArray($value) as $subItem) {
                    $flat[] = $subItem;
                }
            } else {
                $flat[] = $value;
            }
        }

        return $flat;
    }

    /**
     * Its almost same as @see toFlatArray, but gives array values only, not indexes
     *
     * @param array $values
     * @return array
     */
    protected function getBagEnds(array $values): array
    {
        $bagEnds = [];
        foreach ($values as $value) {
            if (\is_array($value)) {
                foreach ($this->getBagEnds($value) as $subItem) {
                    $bagEnds[] = $subItem;
                }
            } else {
                $bagEnds[] = $value;
            }
        }

        return $bagEnds;
    }

    /**
     * @param array $additionalParameters
     * @return string
     */
    public function getRequestUrl(array $additionalParameters = []): string
    {
        if (!$additionalParameters) {
            return $_SERVER['REQUEST_URI'] ?? '';
        }
        $values = \array_merge($_GET, $additionalParameters); // values from GET can be overwritten

        return $this->buildUrl($values);
    }

    /**
     * @param array $values
     * @return string
     */
    private function buildUrl(array $values): string
    {
        $query = [];
        foreach ($values as $name => $value) {
            foreach ($this->buildUrlParts($name, $value) as $pair) {
                $query[] = $pair;
            }
        }
        $urlParts = \parse_url($_SERVER['REQUEST_URI'] ?? '');
        $host = '';
        if (!empty($urlParts['scheme'] && !empty($urlParts['host']))) {
            $host = $urlParts['scheme'] . '://' . $urlParts['host'];
        }
        $queryString = '';
        if ($query) {
            $queryString = '/?' . \implode('&', $query);
        }

        return $host . $queryString;
    }

    /**
     * @param string $name
     * @param array|string $value
     * @return array|string[]
     */
    private function buildUrlParts(string $name, $value): array
    {
        if (!\is_array($value)) {
            return [\urlencode($name) . '=' . \urlencode($value)];
        }
        $pairs = [];
        foreach ((array)$value as $part) {
            foreach ($this->buildUrlParts($name . '[]', $part) as $pair) {
                $pairs[] = $pair;
            }
        }

        return $pairs;
    }

    /**
     * @param array $except
     * @return string
     */
    public function getCurrentValuesAsHiddenInputs(array $except = []): string
    {
        $html = [];
        foreach ($this->getSelectedValues() as $name => $value) {
            if (\in_array($name, $except, true)) {
                continue;
            }
            if (!\is_array($value)) {
                $html[] = "<input type='hidden' name='" . \htmlspecialchars($name) . "' value='" . \htmlspecialchars($value) . "'>";
            } else {
                foreach ($value as $item) {
                    $html[] = "<input type='hidden' name='" . \htmlspecialchars($name) . "[]' value='" . \htmlspecialchars($item) . "'>";
                }
            }
        }

        return \implode("\n", $html);
    }

    /**
     * @return array
     */
    public function getSelectedValues(): array
    {
        return $this->getMemory()->getIterator()->getArrayCopy();
    }

    /**
     * @param array $exceptParameterNames
     * @return string
     */
    public function getRequestUrlExcept(array $exceptParameterNames): string
    {
        $values = $_GET;
        foreach ($exceptParameterNames as $name) {
            if (\array_key_exists($name, $values)) {
                unset($values[$name]);
            }
        }

        return $this->buildUrl($values);
    }
}
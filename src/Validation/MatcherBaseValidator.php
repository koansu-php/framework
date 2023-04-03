<?php
/**
 *  * Created by mtils on 05.02.2023 at 09:18.
 **/

namespace Koansu\Validation;

use Koansu\Core\Map;
use Koansu\Expression\Constraint;
use Koansu\Expression\ConstraintGroup;
use Koansu\Validation\Contracts\Validation;
use Koansu\Validation\Contracts\Validator as ValidatorContract;
use RuntimeException;

use function call_user_func;
use function explode;
use function in_array;
use function iterator_to_array;
use function strpos;
use function substr;

class MatcherBaseValidator
{
    /**
     * @var Matcher
     */
    protected $matcher;

    /**
     * @var string[]
     */
    protected $required_rules = ['required', 'required_if', 'required_unless'];

    /**
     * @var callable
     */
    private $caster;

    /**
     * @var JsonPathIterator
     */
    private $jsonPathSplitter;

    public function __construct(Matcher $matcher, callable $caster=null)
    {
        $this->matcher = $matcher;
        $this->caster = $caster ?: new Caster();
        $this->jsonPathSplitter = new JsonPathIterator();
    }

    /**
     * Perform validation by the base validator. Reimplement this method
     * to use it with your favourite base validator.
     *
     * @param Validation    $validation
     * @param array         $input
     * @param array         $baseRules
     * @param object|null   $ormObject (optional)
     * @param array         $formats (optional)
     *
     * @return array
     **/
    public function validate(Validation $validation, array $input, array $baseRules, object $ormObject=null, array $formats=[]) : array
    {
        $validated = [];

        foreach ($baseRules as $key=>$rule) {
            $values = $this->extractValue($input, $key);
            $requiredFailed = false;

            if (!$this->checkRequiredRules($rule, $input, $key, $validation, $values)) {
                $requiredFailed = true;
            }

            foreach ($values as $path=>$value) {
                $isValid = true;
                foreach ($rule as $name=>$args) {
                    if (in_array($name, $this->required_rules) || $requiredFailed) {
                        continue;
                    }
                    if (!$this->check($value, [$name=>$args], $ormObject, $formats)) {
                        $isValid = false;
                        $validation->addFailure($path, $name, $args);
                    }
                }

                $casted = $isValid ? $this->cast($value, $rule, $ormObject, $formats) : $value;

                if ($casted !== null) {
                    Map::set($validated, $this->splitPath($path), $casted);
                }

            }

        }

        return $validated;
    }

    /**
     * Alias for self::validate(). For simple usage as base validator in Validator.
     *
     * @param Validation    $validation
     * @param array         $input
     * @param array         $baseRules
     * @param object|null   $ormObject (optional)
     * @param array         $formats (optional)
     *
     * @return array
     **/
    public function __invoke(Validation $validation, array $input, array $baseRules, object $ormObject=null, array $formats=[]) : array
    {
        return $this->validate($validation, $input, $baseRules, $ormObject, $formats);
    }

    /**
     * Cast the value into something a repository can process.
     *
     * @param mixed                                   $value
     * @param ConstraintGroup|Constraint|array|string $rule
     * @param object|null                             $ormObject (optional)
     * @param array                                   $formats (optional)
     *
     * @return mixed
     * @noinspection PhpMissingParamTypeInspection
     */
    public function cast($value, $rule, $ormObject=null, array $formats=[])
    {
        return call_user_func($this->caster, $value, $rule, $ormObject, $formats);
    }

    /**
     * Check if $value matches $rule.
     *
     * @param mixed                                   $value
     * @param ConstraintGroup|Constraint|array|string $rule
     * @param object|null                             $ormObject (optional)
     * @param array                                   $formats (optional)
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function check($value, $rule, object $ormObject = null, array $formats=[]) : bool
    {
        if (!$this->matcher) {
            $this->matcher = new Matcher();
        }
        if (isset($rule['date']) && isset($formats[ValidatorContract::DATE_FORMAT])) {
            $rule['date'] = [$formats[ValidatorContract::DATE_FORMAT]];
        }
        return $this->matcher->match($value, $rule, $ormObject);
    }

    /**
     * Select the value by key. If key contains wildcards select the passed
     * values.
     * Set the "path" to each value as a key in the array.
     * This allows keys like [12].address.street for keys like [*].address.*
     *
     * @param array $input
     * @param string $key
     *
     * @return array
     */
    protected function extractValue(array $input, string $key) : array
    {
        if ($this->isSelector($key)) {
            $iterator = (new JsonPathIterator($input, $key))->setKeyPrefix('');
            return iterator_to_array($iterator);

        }
        if (!isset($input[$key])) {
            return [$key=>null];
        }
        return [$key=>$input[$key]];
    }

    /**
     * Check all required rules and return true if other rules should be checked
     * after.
     *
     * @param array $rule
     * @param array $input
     * @param string $key
     * @param Validation $validation
     * @param array $extracted
     * @return bool
     */
    protected function checkRequiredRules(array $rule, array $input, string $key, Validation $validation, array $extracted=[]) : bool
    {
        // Check required before others to skip rest if missing
        if (isset($rule['required']) && !$this->checkRequired($input, $key, $extracted)) {
            $validation->addFailure($key, 'required', []);
            return false;
        }

        if (isset($rule['required_if']) && !$this->checkRequiredIf($input, $key, $rule['required_if'])) {
            $validation->addFailure($key, 'required_if', $rule['required_if']);
            return false;
        }

        if (isset($rule['required_unless']) && !$this->checkRequiredUnless($input, $key, $rule['required_unless'])) {
            $validation->addFailure($key, 'required_unless', $rule['required_unless']);
            return false;
        }

        // Check if not required other rules are ignored
        if (!isset($rule['required']) && !$this->checkRequired($input, $key, $extracted)) {
            return false;
        }

        return true;
    }

    /**
     * @param array $input
     * @param string $key
     * @return bool
     */
    protected function checkRequired(array $input, string $key, array $extracted=[]) : bool
    {
        $constraint = ['required'=>[]];

        if (!$this->isSelector($key)) {
            return isset($input[$key]) && $this->check($input[$key], $constraint);
        }

        $value = $extracted ?: $this->extractValue($input, $key);

        if (isset($value[$key])) {
            return $this->check($value[$key], $constraint);
        }

        return $this->check($value, $constraint);
    }

    /**
     * Check if value is present but only if another is also.
     *
     * @param array $input
     * @param string $key
     * @param array $args
     * @return bool
     */
    protected function checkRequiredIf(array $input, string $key, array $args=[]) : bool
    {
        if (!isset($args[0])) {
            throw new RuntimeException("Validation rules required_if and required_unless need another field name arg as minimum");
        }
        $other = $args[0];
        $otherValue = $input[$other] ?? null;

        $otherMatches = isset($args[1]) ? $args[1] == $otherValue : $this->checkRequired($input, $other);

        if (!$otherMatches) {
            return true;
        }
        return $this->checkRequired($input, $key);
    }

    /**
     * Check if value is present but only if other is not.
     *
     * @param array $input
     * @param string $key
     * @param array $args
     * @return bool
     */
    protected function checkRequiredUnless(array $input, string $key, array $args=[]) : bool
    {
        if (!isset($args[0])) {
            throw new RuntimeException("Validation rules required_if and required_unless need another field name arg as minimum");
        }
        $other = $args[0];
        $otherValue = $input[$other] ?? null;

        $otherMatches = isset($args[1]) ? $args[1] == $otherValue : $this->checkRequired($input, $other);

        if ($otherMatches) {
            return true;
        }
        return $this->checkRequired($input, $key);
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function isSelector(string $key) : bool
    {
        return strpos($key,'*') || strpos($key,'.');
    }

    /**
     * Check if this is a json path selector for indexed arrays. ([0] or [*] or so)
     *
     * @param string $path
     * @return bool
     */
    protected function isIndexedPath(string $path) : bool
    {
        return strpos($path, '[') !== false;
    }

    /**
     * @param string $path
     *
     * @return string[]
     */
    protected function splitPath(string $path) : array
    {
        if (!$this->isIndexedPath($path)) {
            return explode('.', $path);
        }
        if (!$this->jsonPathSplitter) {
            $this->jsonPathSplitter = new JsonPathIterator();
        }

        $segments = [];

        foreach($this->jsonPathSplitter->splitPath($path) as $segment) {
            if (strpos($segment, '[') !== 0) {
                $segments[] = $segment;
                continue;
            }
            $segments[] = (int)substr($segment, 1, -1);
        }

        return $segments;

    }
}
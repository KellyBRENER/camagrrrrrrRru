<?php

final class Validation {
    public static function text($value, $maxLength, $field, $allowEmpty = false) {
        if (!is_string($value) || preg_match('//u', $value) !== 1 || strpos($value, "\0") !== false) {
            throw new InvalidArgumentException($field . ' : texte invalide.');
        }
        // Validate before trim: do not silently discard an oversized input.
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new InvalidArgumentException($field . ' : longueur maximale de ' . $maxLength . ' caractères.');
        }
        if (!$allowEmpty && trim($value) === '') {
            throw new InvalidArgumentException($field . ' : valeur requise.');
        }
        return $value;
    }

    public static function integer($value, $min, $max, $field) {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string) $value)) {
            throw new InvalidArgumentException($field . ' : entier invalide.');
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($number === false) {
            throw new InvalidArgumentException($field . ' : valeur hors limites.');
        }
        return $number;
    }

    public static function number($value, $min, $max, $field) {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
            throw new InvalidArgumentException($field . ' : nombre invalide.');
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < $min || $number > $max) {
            throw new InvalidArgumentException($field . ' : valeur hors limites.');
        }
        return $number;
    }

    public static function identifier($value, $field) {
        self::text($value, 50, $field);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $value)) {
            throw new InvalidArgumentException($field . ' : identifiant invalide.');
        }
        return $value;
    }
}

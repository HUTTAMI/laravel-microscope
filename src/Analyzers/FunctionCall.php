<?php

namespace Imanghafoori\LaravelMicroscope\Analyzers;

class FunctionCall
{
    protected static function getNextToken($tokens, $i)
    {
        $i++;
        $nextToken = $tokens[$i] ?? '_';
        if ($nextToken[0] == T_WHITESPACE) {
            $i++;
            $nextToken = $tokens[$i] ?? null;
        }

        return [$nextToken, $i];
    }

    protected static function getPrevToken($tokens, $i)
    {
        $i--;
        $token = $tokens[$i];
        if ($token[0] == T_WHITESPACE) {
            $i--;
            $token = $tokens[$i];
        }

        return [$token, $i];
    }

    protected static function isAfterWhiteSpace($prev1)
    {
        return $prev1 == T_WHITESPACE;
    }

    protected static function isAfterOp($prev1, $prev2, $operators)
    {
        if (in_array($prev1, $operators)) {
            return true;
        }

        if ($prev1 == T_WHITESPACE && in_array($prev2, $operators)) {
            return true;
        }

        return false;
    }

    static function isSolidString($tokens)
    {
        [$nextToken, $i] = self::getNextToken($tokens, 0);
        return ($tokens[0][0] == T_CONSTANT_ENCAPSED_STRING) && ($nextToken !== '.');
    }

    /**
     * @param $funcName
     * @param $tokens
     * @param $i
     *
     * @return array|bool
     */
    static function isGlobalFunctionCall($funcName, &$tokens, $i)
    {
        $token = $tokens[$i];

        if ($token[0] != '(') {
            return null;
        }

        [$method, $p] = self::getPrevToken($tokens, $i);

        $ops = [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NEW, T_FUNCTION];
        [$prev, $p2] = self::getPrevToken($tokens, $p);

        if ($method[0] != T_STRING || $method[1] != $funcName || in_array($prev, $ops)) {
            return null;
        }

        return $method;
    }

    static function isStaticFunctionCall($methodName, &$tokens, $i, $className = null)
    {
        $token = $tokens[$i];

        if ($token[0] != '(') {
            return null;
        }

        [$method, $p] = self::getPrevToken($tokens, $i);
        [$operator, $p2] = self::getPrevToken($tokens, $p);
        [$classToken, $p3] = self::getPrevToken($tokens, $p2);

        if ($method[0] != T_STRING ||
            $method[1] != $methodName ||
            $operator[0] != T_DOUBLE_COLON
        ) {
            return null;
        }
        if ($className &&
            $classToken[0] != T_STRING ||
            $classToken[1] != $className
        ) {
            return null;
        }

        return [$method, $operator, $classToken];
    }

    /**
     * @param  array  $tokens
     * @param  int  $i the index of the "(" token.
     *
     * @return array
     */
    public static function readParameters(&$tokens, $i)
    {
        $params = [];
        $p = 0;
        $level = 1;
        while (true) {
            [$nextToken, $i] = self::getNextToken($tokens, $i);

            if (in_array($nextToken, ['[', '(', '{'])) {
                $level++;
            }

            if (in_array($nextToken, [']', ')', '}'])) {
                $level--;
            }

            if ($level == 0 && $nextToken == ')') {
                break;
            }

            if ($level == 1 && $nextToken == ',') {
                $p++;
                continue;
            }

            $params[$p][] = $nextToken;
        }

        return $params;
    }
}
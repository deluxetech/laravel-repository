<?php

declare(strict_types=1);

use PhpCsFixer\Preg;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

final class LineBreakAfterStatementsFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    /**
     * There is no 'do', 'cause the processing of the 'while' also includes "do {} while ();" construction
     */
    private const STATEMENTS = [
        T_IF,
        T_SWITCH,
        T_FOR,
        T_FOREACH,
        T_WHILE,
        T_TRY,
    ];

    public function getName(): string
    {
        return 'PhpCsFixer/line_break_after_statements_fixer';
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound(self::STATEMENTS);
    }

    public function getPriority(): int
    {
        // for the best result should be run after the BracesFixer
        return -26;
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Ensures that there is one blank line above the control statements.',
            [
                new CodeSample(
                    '<?php
class Foo
{
    /**
     * @return null
     */
    public function foo() {
        do {
            // ...
        } while (true);
        foreach (["foo", "bar"] as $str) {
            // ...
        }
        if (true === false) {
            // ...
        }
        foreach (["foo", "bar"] as $str) {
            if ($str === "foo") {
                // smth
            }

        }
        while (true) {
            // ...
        }
        switch("123") {
            case "123":
                break;
        }
        try {
            // ...
        } catch (Throwable $e) {
            // ...
        }
        $a = "next statement";
    }
}
',
                ),
            ],
        );
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(self::STATEMENTS)) {
                continue;
            }

            $endStatementIndex = $this->findStatementEnd($tokens, $index);
            $nextStatementIndex = $tokens->getNextMeaningfulToken($endStatementIndex);

            if ($nextStatementIndex === null || $tokens[$nextStatementIndex]->equals('}')) {
                continue;
            }

            $this->ensureBlankLine($tokens, $endStatementIndex + 1);
        }
    }

    private function ensureBlankLine(Tokens $tokens, int $index): void
    {
        $content = $tokens[$index]->getContent();

        // Apply fix only in the case when the count lines do not equals to expected
        if (substr_count($content, "\n") === 2) {
            return;
        }

        // The final bit of the whitespace must be the next statement's indentation
        Preg::matchAll('/[^\n\r]+[\r\n]*/', $content, $matches);
        $lines = $matches[0];
        $eol = $this->whitespacesConfig->getLineEnding();
        $tokens[$index] = new Token([T_WHITESPACE, str_repeat($eol, 2) . end($lines)]);
    }

    private function findStatementEnd(Tokens $tokens, int $index): int
    {
        $nextIndex = $tokens->getNextMeaningfulToken($index);
        $nextToken = $tokens[$nextIndex];

        if ($nextToken->equals('(')) {
            $parenthesisEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $nextIndex);
            $possibleBeginBraceIndex = $tokens->getNextNonWhitespace($parenthesisEndIndex);
        } else {
            $possibleBeginBraceIndex = $nextIndex;
        }

        // `do {} while ();`
        if ($tokens[$index]->isGivenKind(T_WHILE) && $tokens[$possibleBeginBraceIndex]->equals(';')) {
            return $possibleBeginBraceIndex;
        }

        $possibleBeginBrace = $tokens[$possibleBeginBraceIndex];

        if ($possibleBeginBrace->equals('{')) {
            $blockEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $possibleBeginBraceIndex);
        } else {
            $blockEnd = $tokens->getNextTokenOfKind($possibleBeginBraceIndex, [';']);
        }

        $nextStatementIndex = $tokens->getNextMeaningfulToken($blockEnd);

        if ($nextStatementIndex === null) {
            return $blockEnd;
        }

        // `if () {} elseif {}`
        if ($tokens[$nextStatementIndex]->isGivenKind(T_ELSEIF)) {
            return $this->findStatementEnd($tokens, $nextStatementIndex);
        }

        // `if () {} else if {}` or simple `if () {} else {}`
        if ($tokens[$nextStatementIndex]->isGivenKind(T_ELSE)) {
            $nextNextStatementIndex = $tokens->getNextMeaningfulToken($nextStatementIndex);

            if ($tokens[$nextNextStatementIndex]->isGivenKind(T_IF)) {
                return $this->findStatementEnd($tokens, $nextNextStatementIndex);
            }

            return $this->findStatementEnd($tokens, $nextStatementIndex);
        }

        // `try {} catch {} finally {}`
        if ($tokens[$nextStatementIndex]->isGivenKind([T_CATCH, T_FINALLY])) {
            return $this->findStatementEnd($tokens, $nextStatementIndex);
        }

        return $blockEnd;
    }
}

<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Polkurier\Webservice\Response\TwoFactorToken;

/**
 * Collects everything an admin action has to report into one message, however many things the
 * action did. The parts are shown together, in order, at the most severe level among them. An
 * action gathers its parts here and sends them once, so the admin gets one flash per action rather
 * than one per row or per step.
 */
class FlashMessageCollector
{
    private const int SUCCESS = 0;
    private const int NOTICE = 1;
    private const int WARNING = 2;
    private const int ERROR = 3;

    /**
     * @var string[]
     */
    private array $parts = [];

    private int $level = self::SUCCESS;

    public function success(Phrase|string $text): self
    {
        return $this->add($text, self::SUCCESS);
    }

    public function notice(Phrase|string $text): self
    {
        return $this->add($text, self::NOTICE);
    }

    public function warning(Phrase|string $text): self
    {
        return $this->add($text, self::WARNING);
    }

    public function error(Phrase|string $text): self
    {
        return $this->add($text, self::ERROR);
    }

    /**
     * Adds the one message, if there is anything to say, and starts over.
     */
    public function send(ManagerInterface $messages): void
    {
        if ($this->parts === []) {
            return;
        }
        // The parts are translated already, or come from Polkurier: shown as they are.
        $text = \implode(' ', $this->parts);
        match ($this->level) {
            self::ERROR => $messages->addErrorMessage($text),
            self::WARNING => $messages->addWarningMessage($text),
            self::NOTICE => $messages->addNoticeMessage($text),
            default => $messages->addSuccessMessage($text),
        };
        $this->parts = [];
        $this->level = self::SUCCESS;
    }

    /**
     * Says where a confirmation code was sent and until when it's valid. "Where" is the name of the
     * method Polkurier sent it by: a partly masked phone number or e-mail address.
     */
    public static function codeSent(TwoFactorToken $token): string
    {
        $where = \trim((string)$token->getTransportMethod()?->getName()) ?: \trim($token->getPhoneNumber());
        $until = $token->getExpiresAt()->format('H:i');

        return $where !== ''
            ? (string)__('Code sent to %1; valid until %2.', $where, $until)
            : (string)__('Code sent; valid until %1.', $until);
    }

    private function add(Phrase|string $text, int $level): self
    {
        $text = \trim((string)$text);
        if ($text !== '') {
            $this->parts[] = $text;
            $this->level = \max($this->level, $level);
        }

        return $this;
    }
}

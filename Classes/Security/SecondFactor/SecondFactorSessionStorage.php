<?php
namespace Networkteam\Neos\FrontendLogin\Security\SecondFactor;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Neos\Flow\Session\SessionInterface;
use Neos\Flow\Session\SessionManagerInterface;

/**
 * Second factor state of the current session
 *
 * The state is bound to the account it was created for, so a state left over from another account is ignored.
 *
 * @Flow\Scope("singleton")
 */
class SecondFactorSessionStorage
{
    const SESSION_KEY = 'Networkteam.Neos.FrontendLogin:SecondFactor';

    const STATUS_NEEDED = 'NEEDED';
    const STATUS_AUTHENTICATED = 'AUTHENTICATED';

    /**
     * @Flow\Inject
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    public function startChallenge(Account $account, string $redirectTarget): void
    {
        $this->putData([
            'account' => $this->getAccountKey($account),
            'status' => self::STATUS_NEEDED,
            'redirectTarget' => $redirectTarget
        ]);
    }

    public function markAuthenticated(Account $account): void
    {
        $this->putData([
            'account' => $this->getAccountKey($account),
            'status' => self::STATUS_AUTHENTICATED
        ]);
    }

    /**
     * @return string|null One of the STATUS_* constants, null if no state exists for the account
     */
    public function getStatus(Account $account): ?string
    {
        $data = $this->getData();
        if (($data['account'] ?? null) !== $this->getAccountKey($account)) {
            return null;
        }

        return $data['status'] ?? null;
    }

    public function isPending(Account $account): bool
    {
        return $this->getStatus($account) === self::STATUS_NEEDED;
    }

    public function getRedirectTarget(Account $account): ?string
    {
        if (!$this->isPending($account)) {
            return null;
        }

        return $this->getData()['redirectTarget'] ?? null;
    }

    public function reset(): void
    {
        $session = $this->sessionManager->getCurrentSession();
        if ($session->isStarted()) {
            $session->putData(self::SESSION_KEY, []);
        }
    }

    protected function getData(): array
    {
        $session = $this->sessionManager->getCurrentSession();
        if (!$session->isStarted() || !$session->hasKey(self::SESSION_KEY)) {
            return [];
        }

        $data = $session->getData(self::SESSION_KEY);

        return is_array($data) ? $data : [];
    }

    protected function putData(array $data): void
    {
        $session = $this->getStartedSession();
        $session->putData(self::SESSION_KEY, $data);
    }

    protected function getStartedSession(): SessionInterface
    {
        $session = $this->sessionManager->getCurrentSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }

    protected function getAccountKey(Account $account): string
    {
        return $account->getAuthenticationProviderName() . ':' . $account->getAccountIdentifier();
    }
}

<?php

namespace App\Notifications;

/**
 * Notice about a proposal moving through submit, review and decision. It is stored under
 * its own type so the proposal notices can be told apart from the other project notices.
 */
class ProposalWorkflowNotification extends ProjectActivityNotification {}

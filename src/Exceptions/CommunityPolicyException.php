<?php

namespace FileImporter\Exceptions;

/**
 * Exception thrown when an import is stopped because it does not mention a compatible license, or
 * contains a forbidden template or category. This is typically a roadblock and should *not* be
 * resolved by the user.
 *
 * @license GPL-2.0-or-later
 */
class CommunityPolicyException extends LocalizedImportException {

}

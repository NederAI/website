<?php
namespace Core;

class Error extends \Exception {
    // Indicates who or what is at fault: 'system' or 'user'
    protected $blame;
    // A more detailed human-friendly error message.
    protected $humanDetails;
    // Machine-readable error code (generated if not provided)
    protected $machineCode;
    // Optional array for additional debug information
    protected $machineDetails;
    // Http response code
    protected $httpCode;

    /**
     * Constructor.
     *
     * @param string      $blame          Who is to blame ('system' or 'user').
     * @param string      $message        A brief message identifying the type of error.
     * @param string      $humanDetails   A human-friendly message describing exactly what went wrong.
     * @param array       $machineDetails Assoc array of details used for programmatic handling.
     * @param int         $httpCode       HTTP status code (default 500).
     * @param string|null $machineCode    Optional machine-readable error code. If not provided, one is generated.
     */
    public function __construct(
        string $blame,
        string $message,
        string $humanDetails = '',
        array $machineDetails = [],
        int $httpCode = 500,
        ?string $machineCode = null
    ) {
        // Generate a machine code if none is provided.
        if ($machineCode === null) {
            $machineCode = substr(hash('sha256', $message), 0, 8);
        }
        
        $this->blame = $blame;
        $this->humanDetails = $humanDetails;
        $this->machineCode = $machineCode;
        $this->machineDetails = $machineDetails;
        $this->httpCode = $httpCode;
        
        // Call the parent constructor with the brief message.
        parent::__construct($message);
    }

    /**
     * Retrieve who is to blame ('system' or 'user').
     *
     * @return string
     */
    public function getBlame(): string {
        return $this->blame;
    }
    
    /**
     * Retrieve the human-friendly error details.
     *
     * @return string
     */
    public function getHumanDetails(): string {
        return $this->humanDetails;
    }
    
    /**
     * Retrieve the machine-readable error code.
     *
     * @return string
     */
    public function getMachineCode(): string {
        return $this->machineCode;
    }
    
    /**
     * Retrieve additional debug information.
     *
     * @return array
     */
    public function getMachineDetails(): array {
        return $this->machineDetails;
    }
    
    /**
     * Retrieve additional debug information.
     *
     * @return int
     */
    public function getHttpCode(): int {
        return $this->httpCode;
    }
}
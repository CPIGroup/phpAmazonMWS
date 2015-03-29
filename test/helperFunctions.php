<?php
    /**
     * Contains helper functions needed for the unit tests
     */

    date_default_timezone_set( 'America/New_York' );

    /**
     * setupDummyConfigFile
     *
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    function setupDummyConfigFile()
    {

        if (!file_exists( 'amazon-config.php' )) {
            copy( 'amazon-config.default.php', 'amazon-config.php' );
            if (!touch( 'dummy.used' )) {
                die( "Couldn't create dummy flag file! Check permissions on the project root!" );
            }
        }
    }

    /**
     * removeDummyConfigFile
     *
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    function removeDummyConfigFile()
    {

        if (file_exists( 'dummy.used' )) {
            unlink( 'amazon-config.php' );
        }
    }

    /**
     * Resets log for next test
     */
    function resetLog()
    {

        file_put_contents( 'test/log.txt', '' );
    }

    /**
     * gets the log contents
     */
    function getLog()
    {

        return file_get_contents( 'test/log.txt' );
    }

    /**
     * gets log and returns messages in an array
     *
     * @param string $s pre-fetched log contents
     *
     * @return array list of message strings
     */
    function parseLog( $s = null )
    {

        if (!$s) {
            $s = getLog();
        }
        $temp = explode( "\n", $s );
        array_pop( $temp );

        $return = [ ];
        foreach ($temp as $x) {
            if ($x != '') {
                $tempo = explode( '] ', $x );
                if (isset( $tempo[ 1 ] )) {
                    $return[ ] = trim( $tempo[ 1 ] );
                }
            }
        }

        return $return;
    }

?>

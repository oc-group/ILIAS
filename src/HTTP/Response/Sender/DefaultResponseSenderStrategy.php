<?php

namespace ILIAS\HTTP\Response\Sender;

use Psr\Http\Message\ResponseInterface;

/******************************************************************************
 *
 * This file is part of ILIAS, a powerful learning management system.
 *
 * ILIAS is licensed with the GPL-3.0, you should have received a copy
 * of said license along with the source code.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 *      https://www.ilias.de
 *      https://github.com/ILIAS-eLearning
 *
 *****************************************************************************/
/**
 * Class DefaultResponseSenderStrategy
 *
 * The default response sender strategy rewinds the current body
 * stream and sends the entire stream out to the client.
 *
 * @author  Nicolas Schäfli <ns@studer-raimann.ch>
 */
class DefaultResponseSenderStrategy implements ResponseSenderStrategy
{
    /**
     * Sends the rendered response to the client.
     *
     * @param ResponseInterface $response The response which should be send to the client.
     *
     * @throws ResponseSendingException Thrown if the response was already sent to the client.
     */
    public function sendResponse(ResponseInterface $response): void
    {
        //check if the request is already send
        if (headers_sent()) {
            throw new ResponseSendingException("Response was already sent.");
        }

        //set status code
        http_response_code($response->getStatusCode());

        //render all headers
        foreach (array_keys($response->getHeaders()) as $key) {
            // See Mantis #37385.
            if (strtolower($key) === 'set-cookie') {
                foreach ($response->getHeader($key) as $header) {
                    header("$key: " . $header, false);
                }
            } else {
                header("$key: " . $response->getHeaderLine($key));
            }
        }

        //rewind body stream
        $response->getBody()->rewind();

        //detach psr-7 stream from resource
        $resource = $response->getBody()->detach();

        $sendStatus = false;

        if (is_resource($resource)) {
            set_time_limit(0);
            try {
                ob_end_clean(); // see https://mantis.ilias.de/view.php?id=32046
            } catch (\Throwable $t) {
            }

            $sendStatus = fpassthru($resource);

            //free up resources
            fclose($resource);
        }

        //check if the body was successfully send to the client
        if ($sendStatus === false) {
            throw new ResponseSendingException("Could not send body content to client.");
        }
    }
}

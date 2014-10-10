<?php
  /*
  -----------------------------------------------------------
  FILE NAME: restServer.php
  
  Copyright (c) 2011 Miami University, All Rights Reserved.
  
  Miami University grants you ("Licensee") a non-exclusive, royalty free,
  license to use, modify and redistribute this software in source and
  binary code form, provided that i) this copyright notice and license
  appear on all copies of the software; and ii) Licensee does not utilize
  the software in a manner which is disparaging to Miami University.
  
  This software is provided "AS IS" and any express or implied warranties,
  including, but not limited to, the implied warranties of merchantability
  and fitness for a particular purpose are disclaimed. It has been tested
  and is believed to work as intended within Miami University's
  environment. Miami University does not warrant this software to work as
  designed in any other environment.
  
  AUTHOR: Kent Covert
  
  DESCRIPTION: dispather for rest web services written in PHP
  
  TABLES/USAGE: none
      
  INPUT:
     PARAMETERS:
       webservicesuri - generally used for debugging purposes only when the
                        web server has not yet been configured to pass the 
                        resource URI to this appliction
       other - other parameters may be passed depending on the resource being
                        called.
     FILES:      none
     STDIN:      none
     CLASSES:
       renderer_xxxx.php - where 'xxxx' is the format the output data is to be
                        rendered with (xml, json, form, etc.)
    
  OUTPUT:	
     RETURN VALUES: none
     FILES:         none
     STDOUT:        none
    
  SYSTEM: restServer
  
  ENVIRONMENT DEPENDENCIES: none
  
  PROGRAM DEPENDENCIES: none
  
  AUDIT TRAIL:
  
  DATE		PRJ-TSK          UniqueID
  Description:
  
  2/26/2011                covertka
  Description:  Initial Program
    
  -------------------------------------------------------------------
  */

  # This is the main dispatcher code.
  # Gather information about the request, instantiate the resource, and call the
  # appropriate method within that resource.
  
  # Include library items from Marmot that we need.  We don't want all the overhead
  # of Marmot, so there is not automatic inclusion.
  include_once('mu_host_specific.php');
  include_once('mu_stdlib.php');
  
  include_once('timer.php');
  $timer = new timer();

  include_once('webservice.php');
  include_once('resource.php');
  include_once ('resource_payload.php');
   
  $timer->checkpoint('classes loaded');

  #
  # Configuration items
  #
  # resourceSearchPaths - an array of directories to use as a starting point
  #   for finding resources
  $resourceSearchPaths = array($_SERVER['DOCUMENT_ROOT'], '../..', '..', '.');
  #
  # resourceSearchFilenames - an array of filenames to use when searching
  #   for resources
  $resourceSearchFilenames = array('api.php');
  #
  # rendererSearchPaths - an array of directories to use as a starting point
  #   for finding renderers
  $rendererSearchPaths = array('.');

  list($resourcePath, $resourceName, $uriParams, $format) = parseURI();
  $timer->checkpoint('URI parsed');

  // Check to make sure we've got permissions to complete this service request
  if ($errorMessage = checkSecurity($resourceName, $format)) {
    implementationError(403, $errorMessage);
  }

  # instantiate the resource
  include_once($resourcePath);
  $timer->checkpoint('resource loaded');
  #todo - handle when resource class can't be found
  
  $resource = new $resourceName($format, $uriParams);
  $timer->checkpoint('resource created');
  
  
  # call the appropriate method based on the HTTP request method
  if($_REQUEST['_method']) {
    $method = strtoupper($_REQUEST['_method']);
  } else {
    $method = strtoupper($_SERVER['REQUEST_METHOD']);
  }

  $timer->checkpoint('resource setup completed');
  try {
    switch($method) {
      case 'GET':       $data = $resource->get($uriParams);
                        if($data) {
                          $resource->render2($data);
                        }
                        break;


		case 'POST':	if (is_a($resource, 'resource_payload')) {
                               $payload = $resource -> getPayloadData();
                               $data = $resource->post($uriParams, $payload);

                               //var_dump($data);
                         } else {
                        // echo "in else";
                               $data = $resource->post($uriParams);
                          }
                        if($data) {
                          $resource->render2($data);
                        }
                                                  
                        break;
      case 'PUT':       $data = $resource->put($uriParams);
                        if($data) {
                          $resource->render2($data);
                        }
                        break;
      case 'DELETE':    $data = $resource->delete($uriParams);
                        if($data) {
                          $resource->render2($data);
                        }
                        break;
      default:          implementationError(405, $_SERVER['REQUEST_METHOD'] . ' HTTP method not supported');
                        break;
    }
    $timer->checkpoint('restServer post processing completed');
  } catch (Exception $e) {
    $msg = new errorResponse(500, errorResponse::ERROR, $e->getMessage());
    $resource->render2($msg);
  }

  function parseURI() {
    GLOBAL $resourceSearchPaths, $resourceSearchFilenames;
    
    # Find the URI - it was either passed as a parameter or we should use the "real" URI
    #
    # Was the webservice URI passed as a parameter?  If not, use PHP_SELF after validating
    if ($_REQUEST['webserviceuri']) {
      $uriPath = $_REQUEST['webserviceuri'];
    } else {
      # check the PHP_SELF for valid web service information
      $myFilename = basename($_SERVER['SCRIPT_FILENAME']);
      # to be valid, the PHP_SELF should not include this script's filename
      if (strpos($_SERVER['PHP_SELF'], $myFilename) === FALSE) {
        $uriPath = $_SERVER['PHP_SELF'];
      } else {
        # no valid uri found - return error
        implementationError(400, 'No webservice URI found');
      }
    }

    # Here are examples of URLs this method should be able to parse
    #  /resourceName                     - assume .xml and no params
    #  /resourceName/                    - assume .xml and no params
    #  /resourceName.xml                 - assume no params
    #  /resourceName/param1/param2.xml
    #  /resourceName.subname.subname2.xml/param1
    #  /resourceName/param1.subparam1.subparam2.xml - param is 'param1.subparam1.subparam2'

    # split the URI into pieces
    $pathParts = explode('/', trim($uriPath, ' /'));

    # get format and strip format from params
    $lastItemIndex = count($pathParts) - 1;
    if ($lastItem < 0 || strpos($pathParts[$lastItemIndex], '.') === FALSE) {
      $format = 'xml';
    } else {
      $uriParts = pathinfo($pathParts[$lastItemIndex]);
      $format = $uriParts['extension'];
      $pathParts[$lastItemIndex] = $uriParts['filename'];
    }
	
    # find the path to the resource class file
    $uriParams = array();
    while($pathParts) {
      foreach($resourceSearchPaths as $searchPath) {
        foreach($resourceSearchFilenames as $searchFilename) {
          $resourcePath = $searchPath . '/' . implode('/', $pathParts) . '/' . $searchFilename;
          if (file_exists($resourcePath)) {
            break 3;   // jump out of the while loop
          }
        }
      }
      $uriParam = urldecode(array_pop($pathParts));
      array_unshift($uriParams, $uriParam);
    }
    
    if (!$pathParts) {
      implementationError(404, 'resource not found');
    }

    # get resourceName
    $lastItemIndex = count($pathParts) - 1;
    $resourceName = $pathParts[$lastItemIndex];

    return array($resourcePath, $resourceName, $uriParams, $format);
  }

  // Check the referer against settings in configManager to make sure we're allowed
  // to continue.  If a problem is found, an errorMessage is returned.  A blank message
  // is an indication that we can continue.
  function checkSecurity($resourceName, $format) {
    $referer = $_SERVER["HTTP_REFERER"];

    // Disable security check entirely.  Referrer check has no value.  The addition
    // of origin header checking causes an issue when we are coming from ourselves.
    return '';
  }

  function implementationError($httpCode, $message) {
    header(sprintf('HTTP/1.1 %d %s', $httpCode, $message));
    header('Content-type: text/plain');
    printf($message);
    exit;
  }
?>
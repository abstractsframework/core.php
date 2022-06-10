<?php
namespace Abstracts;

use \Abstracts\Route;
use \Abstracts\Utilities;
use \Abstracts\Translation;
use \Abstracts\Initialization;
use \Abstracts\User;
use \Abstracts\Built;

use Exception;

class Render {

  private $config = null;
  
  function __construct($config) {
    $this->config = Initialization::config($config);
    Initialization::headers($this->config);
    Initialization::load($this->config);
  }

  function response() {

    Initialization::response_headers($this->config);
      
    try {
      
      $translation = new Translation();
      
      $user = new User($this->config);
      $session = $user->authenticate();
      
      $message = array(
        400 => $translation->translate("Bad request"),
        404 => $translation->translate("Not found"),
        409 => $translation->translate("No response")
      );
    
      $route = new Route($this->config);
      if ($route->request) {
        if ($route->request->class && $route->request->function) {
          $namespace = "\\Abstracts\\" . $route->request->class;
          $utilities = new Utilities();
          $parameters = $utilities->format_request();
          if (class_exists($namespace)) {
            if (method_exists($namespace, $route->request->function)) {
              $class = new $namespace($this->config, $session, null, $route->request->module);
              try {
                $data = $class->request($route->request->function, $parameters);
                // if (is_null($data) || is_bool($data)) {
                //   if (is_null($data) || $data === false) {
                //     throw new Exception($message[409], 409);
                //   } else {
                //     echo json_encode(
                //       array(
                //         "result" => $data
                //       )
                //     );
                //   }
                // } else {
                //   echo json_encode($data);
                // }
                echo json_encode($data);
              } catch(Exception $e) {
                if ($e->getCode() == 421) {
                  $built = new Built($this->config, $session, null, $route->request->module);
                  $data = $built->request($route->request->function, $parameters);
                  // if (is_null($data) || is_bool($data)) {
                  //   if (is_null($data) || $data === false) {
                  //     throw new Exception($message[409], 409);
                  //   } else {
                  //     echo json_encode(
                  //       array(
                  //         "result" => $data
                  //       )
                  //     );
                  //   }
                  // } else {
                  //   echo json_encode($data);
                  // }
                  echo json_encode($data);
                } else {
                  throw new Exception($e->getMessage(), $e->getCode());
                }
              }
            } else if (method_exists("\\Abstracts\\Built", $route->request->function)) {
              $built = new Built($this->config, $session, null, $route->request->module);
              $data = $built->request($route->request->function, $parameters);
              // if (is_null($data) || is_bool($data)) {
              //   if (is_null($data) || $data === false) {
              //     throw new Exception($message[409], 409);
              //   } else {
              //     echo json_encode(
              //       array(
              //         "result" => $data
              //       )
              //     );
              //   }
              // } else {
              //   echo json_encode($data);
              // }
              echo json_encode($data);
            } else {
              throw new Exception($message[404], 404);
            }
          } else {
            if (method_exists("\\Abstracts\\Built", $route->request->function)) {
              $built = new Built($this->config, $session, null, $route->request->module);
              $data = $built->request($route->request->function, $parameters);
              // if (is_null($data) || is_bool($data)) {
              //   if (is_null($data) || $data === false) {
              //     throw new Exception($message[409], 409);
              //   } else {
              //     echo json_encode(
              //       array(
              //         "result" => $data
              //       )
              //     );
              //   }
              // } else {
              //   echo json_encode($data);
              // }
              echo json_encode($data);
            } else {
              throw new Exception($message[404], 404);
            }
          }
        } else {
          throw new Exception($message[400], 400);
        }
      } else {
        throw new Exception($message[400], 400);
      }
    
    } catch(Exception $e) {
      header($e->getMessage(), true, $e->getCode());
      echo json_encode(
        array(
          "status" => false,
          "code" => $e->getCode(),
          "message" => $e->getMessage()
        )
      );
    }
    
  }

}
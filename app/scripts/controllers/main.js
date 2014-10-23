'use strict';

/**
 * @ngdoc function
 * @name starterApp.controller:MainCtrl
 * @description
 * # MainCtrl
 * Controller of the starterApp
 */
angular.module('starterApp')
  .controller('MainCtrl', function ($scope) {
    $scope.awesomeThings = [
      'HTML5 Boilerplate',
      'AngularJS',
      'Karma'
    ];
  });

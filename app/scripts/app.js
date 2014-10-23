'use strict';

var starterApp = angular.module('starterApp', ['ui.router']);

starterApp.config(function($stateProvider, $urlRouterProvider) {

    $urlRouterProvider.otherwise('/');

    $stateProvider

        // HOME STATES AND NESTED VIEWS ========================================
        .state('home', {
          url: '/',
          templateUrl: 'views/main.html',
          controller: 'MainCtrl',
        })
        .state('about', {
          url: '/about',
          template: '<p class="lead">This is our about page.</p>',
        })

        ; // End of $stateProvider chaining.

});


# Contributing
##General
All pull requests should target the master branch. Commits should not have different code changes mixed together and should have descriptive messages.

##Project Scope
The goal of this project is to provide a library with which the average merchant can communicate with Amazon's MWS services without needing to learn the complex language of the API. When contributing, think about whether or not the contribution is something that the average Amazon merchant would need to intract with Amazon.

Code for handling the merchant's data, such as for generating feeds or data crunching, falls outside of the library's intended scope and, while useful, is best left to separate projects.

##Code Guidelines
The library is written using a custom style and does not follow any particular standard. For the sake of cohesion, changes to the code should be written in this same style. Please do not make changes to the style of the code.

Any new changes should fit within the previously-mentioned goal of the project. New public methods and classes should have names that are easy to understand and use without needing to consult the API's documentation. Try to follow the trend of similar existing functions, such as how methods that send requests to get information from Amazon usually have names that start with "fetch."

New methods should have phpdocs explaining how to use them and how they will affect the options sent in the next request. New classes should have phpdocs explaining their purpose and the workflow for using them. If the class is one that sends requests to Amazon, the documentation should indicate which Amazon actions it can perform and which of its methods are required before a request can be sent. Check the phpdocs on existing classes for examples.

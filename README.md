# pForce
A thin Web Framework for PHP (including site logins) for those who don't want to over-engineer (nor under-engineer) their web apps.  Used by BrainAnnex.org and other web sites/web apps.

Use only what you need.  It doesn't twist your arm to program your web app in any particular way! (In the spirit of Flask, in the Python world.)

pForce: **May the *PHP* Force be with you!**


The pForce Framework was originally developed as part of the "Brain Annex" project, but is now independent of it.

This framework has been in active, continuous use on multiple web apps for years - some components for about a decade - and has been *released as Open Source* in Oct. 2019 as version 4.0

It is very modular, and it currently contains 10 components, with no external dependencies.


[DOCUMENTATION](https://brainannex.org/viewer.php?ac=2&cat=20)


NOTE: the release of the pForce framework to Open Source is the first step towards the promised complete release to open source of the *Knowledge & Media Management* web app Brain Annex (https://brainannex.org)  As of version 4.0, pForce has an independent life from Brain Annex, but continues to be maintained and expanded by the Brain Annex project.


# 10 Components

## *cookies*

Static class to handle COOKIES 


 

## *dbasePDO*

Class for DATABASE-INTERFACE using the PDO functions   


 

## *directedGraphs*

Class to implement a Traversable Directed Acyclic Graph (DAG), consisting of Nodes and Directional Edges between 2 Node.
Each node can carry a set of user-defined "semantics" (such as "name" and "remarks"); likewise for each edges (for example "childSequenceNo") 


 

## *formBuilder*

Classes to easily build HTML forms, as well as "Control Panels" consisting of a table of such forms (each of which is referred to as a "pane")
2 CLASSES:  "controlPanel" and "formBuilder" 


 

## *logging*

Class to log SYSTEM MESSAGES (such as alerts or diagnostics) into a text file or an HTML file

 

## *parameterValidation*

Class with static functions for PARAMETER VALIDATION


 

## *siteAuth*

Class for SITE USER AUTHENTICATION, incl. login/logout management.
Multiple independent websites are supported.

 

## siteMembership

Class for the management of generic user accounts on a site: the underlying database table could have more fields, used by other more site-specific modules
Based on the entity "User-access ID" : identifying a particular membership for some user

 

## *templateEvaluator*

Class for Template Evaluation: bare-bones version of the Python library "Jinja"



## *uploader*

Class to facilitate File Uploads

[DOCUMENTATION](https://brainannex.org/viewer.php?ac=2&cat=20)

# AntiSnapScript
Hello, this was a project of mine for
my Computer Science class.
It is a programming language made for
fun and to replace Snap's block code.
As a person with some programming 
experience, I was highly disappointed
to find out that my High School CS class
is focusing on block coding with the
following website (https://snap.berkeley.edu).

When it was time to make our final
"whatever you want" project, I've
decided to make something that would
replace Snap's block code for the
future experienced students of that CS class.
As a result, I've made the programming
language ASS, which stands for AntiSnapScript.

It is made to be a text-based language
which is then converted to an .xml file
after compilation. The XML file can be imported
into the Snap website as a functional project.

This project was written in PHP, which is pretty bad,
I know, but it was the language with which I had
enough experience to write such a thing at the time.

## Usage
All your ASS code will be going into the resources
folder. Examples like stage.ass and sprite.ass have
already been defined.

stage.ass is a required file which is your
project's central point, while all the other
ASS files are definitions for new sprites
and can be named whatever you want.

When your project is ready, you can run
Loader.php, and a project.xml will be generated
in the resources folder.

You can then import this XML file as a project
at https://snap.berkeley.edu and see your work
in action.

## Contributions
The only examples available right now
are the ones in our resources folder,
and a better introduction to the syntax
of the language would be useful to add.
This project is currently in a heavy W.I.P,
so feel free to contribute any of the things in our TODO.

## ToDo

[X] Initializers

[X] Variables

[X] Listeners

[X] Bool evaluations & Basic nested statements

[X] Basic mathematical operations

[X] Method execution

[] && / || Bool evaluations

[] If Else statements

[] Functions (Blocks in Snap)

[] Major bugs

[] Other improvements



# ZX Metro App

Welcome to Zach's metro application.

## Install the Application

When cloning this application make sure you get the submodule (zx-metro-frontend) too.

Either clone like this: ```git clone --recurse-submodules```

Or run ```git submodule update --init``` after cloning.

To install the dependencies:
```composer install```

Make sure you also install the dependencies for the front end:
```
cd frontend/
npm install
```

Also make sure you build the front end:
```
cd frontend/
webpack
```


## Running in development mode

Open two terminals, in the first run
```
cd $THISREPO/frontend/
npm run dev
```

This will run webpack in watch mode, so any changes to the javascript will be rebuilt.


In the second:

```
cd $THISREPO/
composer start
```

This links the front end folder to the public folder and runs the public/index.php


## Notes

The backend was created using the slim-framework skeleton app.

The frontend was created using the create-react-app tool.


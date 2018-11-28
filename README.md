# Networkteam.Neos.FrontendLogin

Neos package for frontend login inspired by  [Flowpack.Neos.FrontendLogin](https://github.com/Flowpack/Flowpack.Neos.FrontendLogin).
It provides mixins for member area pages and member area root pages 


## Features

* Redirect to login form when a protected uri is requested

## Installation

Install the package via composer

```bash
composer require networkteam/neos-frontendlogin
```

## NodeTypes

**Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot**

The mixin `Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot` defines the root point of a specific
member area.

## Create member area

The packages does not supply a concrete implementation. It does only supply a mixin.
To create a member area you need to define a specific nodeType for member area root pages which 
implement the mixin provided by this package.

### Define specific nodeTypes

You need to define one nodeType for *member area root pages*.

An example configuration could look as follows:

*Packages/Application/Your.Package/Configuration/NodeTypes.MemberAreaRootPage.yaml*
```yaml
'Your.Package:MemberAreaRootPage':
  superTypes:
    'Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot': true
  ui:
    label: 'Member area'
```

### Define fusion objects

For your defined nodeType you need a suitable fusion object. An example configuration could look as follows:

*Packages/Application/Your.Package/Resources/Private/Fusion/MemberAreaRootPage.fusion*
```fusion
# Member Area Root page
prototype(Your.Package:MemberAreaRootPage) < prototype(Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot) {

}
```

### Add pages and login form

Now you can log into Neos backend and create a new member area root page.
Next you need to add a login form content node on a page which is **not of type** `Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot`.
Add additional pages beneath your member area root page.

## Create Frontend Users

To create a new Frontend User you can use the *neos.neos:user:create* command, e.g.
 
```bash
./flow user:create --authentication-provider "Networkteam.Neos.FrontendLogin:Frontend" --roles "Networkteam.Neos.FrontendLogin:FrontendUser"
```

or use the user management module inside Neos backend.
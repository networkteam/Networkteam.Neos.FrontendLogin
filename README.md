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

**Networkteam.Neos.FrontendLogin:Mixins.MemberArea**

The mixin `Networkteam.Neos.FrontendLogin:Mixins.MemberArea` is meant for all secured pages within
the member area. The mixin is used for the policy configuration.

**Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot**

The mixin `Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot` defines the root point of a specific
member area. Only documentNodes of type `Networkteam.Neos.FrontendLogin:Mixins.MemberArea` are allowed 
beneath MemberAreaRoot.

## Create member area

The packages does not supply a concrete implementation. It does only supply mixins.
To create a member area you need to define specific nodeTypes for member area pages and 
member area root pages which implement the mixins provided by this package.

### Define specific nodeTypes

You need to define two nodeTypes, one for *member area root pages* and one for *member area pages*

An example configuration could look as follows:

*Packages/Application/Your.Package/Configuration/NodeTypes.MemberAreaRootPage.yaml*
```yaml
'Your.Package:MemberAreaRootPage':
  superTypes:
    'Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot': true
  ui:
    label: 'Member area'
```

*Packages/Application/Your.Package/Configuration/NodeTypes.MemberAreaPage.yaml*
```yaml
'Your.Package:MemberAreaPage':
  superTypes:
    'Networkteam.Neos.FrontendLogin:Mixins.MemberArea': true
  ui:
    label: 'Member area page'
```

### Define fusion objects

For your defined nodeTypes you need suitable fusion objects. An example configuration could look as follows:

*Packages/Application/Your.Package/Resources/Private/Fusion/MemberAreaRootPage.fusion*
```fusion
# Member Area Root page
prototype(Your.Package:MemberAreaRootPage) < prototype(Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot) {

}
```

*Packages/Application/Your.Package/Resources/Private/Fusion/MemberAreaPage.fusion*
```fusion
# Member Area Page
prototype(Your.Package:MemberAreaPage) < prototype(Networkteam.Neos.FrontendLogin:Mixins.MemberArea) {

}
```

### Add pages and login form

Now you can log into Neos backend and create a new member area root page.
Next you need to add a login form content node on a page which is **not of type** `Networkteam.Neos.FrontendLogin:Mixins.MemberArea`.
Add additional member area pages beneath member area root page.

## Create Frontend Users

To create a new Frontend User you can use the *neos.neos:user:create* command, e.g.
 
```bash
./flow user:create --authentication-provider "Networkteam.Neos.FrontendLogin:Frontend" --roles "Networkteam.Neos.FrontendLoginUser"`
```

or use the user management module inside Neos backend.

# TODO

Eigener Matcher für *Policy.yaml* vom Typ **isDescendantNodeOf** aber mit der Möglichkeit *nodetype-name* anzugeben
Dies ist sinnvoll, damit auch Seiten unterhalb von MemberAreaRoot angelegt werden können, die nicht 
vom Typ `Networkteam.Neos.FrontendLogin:Mixins.MemberArea` sind. 
---
layout: page
title: "Install & Configure"
category: start
date: 2015-05-05 20:13:46
order: 3
---

Now you need to install the stack and configure it so our test client can use it.

### Requirements

<b>You need a 64bits machine.</b> If you have a 32bits it will not work. We use Docker and it requires a 64bits machine.

We will run the stack locally in a Virtual Machine.
We are using VirtualBox in this example, but VMWare works as well. 

Vagrant will be used to start the VM and configure it for you.

More about [Vagrant](https://docs.vagrantup.com/v2/installation/index.html)

#### VirtualBox and Vagrant

Install both applications on your system.

   - [Install VirtualBox](https://www.virtualbox.org/wiki/Downloads) 
   - [Install Vagrant](http://www.vagrantup.com/downloads) 

Vagrant will start an Ubuntu VM on your local machine. In the VM, Vagrant will install Docker. The Docker container will run the whole stack.

#### Production
In production, you won't need Vagrant.
<br>You would deploy N Docker containers on your N machines. <b>Wherever the machines are</b>: Local or Ec2.

Each container would play a different role:

{% include ct_roles.md %}

### Install the stack

Just clone the stack locally somewhere:

    $> git clone https://github.com/sportarchive/CloudTranscode.git

### Configure

Go there to configure the stack

#### Setup your clients

<br>

<p>
<h4><a href="#">Next: Run the stack</a></h4>
</p>

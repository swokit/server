# Linux信号列表

- `SIGHUP` 本信号在用户终端连接(正常或非正常)结束时发出, 通常是在终端的控制进程结束时, 通知同一session内的各个作业, 这时它们与控制终端不再关联。  
  登录Linux时，系统会分配给登录用户一个终端(Session)。在这个终端运行的所有程序，包括前台进程组和后台进程组，一般都属于这个Session。
当用户退出Linux登录时，前台进程组和后台有对终端输出的进程将会收到SIGHUP信号。这个信号的默认操作为终止进程，因此前台进程组和后台有终端输出的进程就会中止。
不过可以捕获这个信号，比如wget能捕获SIGHUP信号，并忽略它，这样就算退出了Linux登录，wget也能继续下载。  
  此外，对于与终端脱离关系的守护进程，这个信号用于通知它重新读取配置文件。
- `SIGINT` 程序终止(interrupt)信号, 在用户键入INTR字符(通常是Ctrl-C)时发出，用于通知前台进程组终止进程。
SIGQUIT 和SIGINT类似, 但由QUIT字符(通常是Ctrl-\)来控制. 进程在因收到SIGQUIT退出时会产生core文件, - `在这个意义上类似于一个程序错误信号。`
- `SIGILL`
- `SIGTRAP`
- `SIGABRT`
- `SIGBUS`
- `SIGFPE`
- `SIGKILL` 用来立即结束程序的运行. 本信号不能被阻塞、处理和忽略。如果管理员发现某个进程终止不了，可尝试发送这个信号。 
- `SIGUSR1` 留给用户使用
- `SIGSEGV`
- `SIGUSR2` 留给用户使用  
- `SIGPIPE` 如果尝试send到一个已关闭的 socket上两次，就会出现此信号，也就是用协议TCP的socket编程，服务器是不能知道客户机什么时候已经关闭了socket，导致还在向该已关 闭的socket上send，导致SIGPIPE。
            而系统默认产生SIGPIPE信号的措施是关闭进程，所以出现了服务器也退出。
- `SIGALRM` 时钟定时信号, 计算的是实际的时间或时钟时间. alarm函数使用该信号. 
- `SIGTERM` 程序结束(terminate)信号, 与SIGKILL不同的是该信号可以被阻塞和处理。通常用来要求程序自己正常退出，shell命令kill缺省产生这个信号。如果进程终止不了，我们才会尝试SIGKILL。 
- `SIGCHLD` 子进程结束时, 父进程会收到这个信号。  
如果父进程没有处理这个信号，也没有等待(wait)子进程，子进程虽然终止，但是还会在内核进程表中占有表项，这时的子进程称为僵尸进程。
这种情况我们应该避免(父进程或者忽略SIGCHILD信号，或者捕捉它，或者wait它派生的子进程，或者父进程先终止，这时子进程的终止自动由init进程来接管)。
- `SIGCONT` 让一个停止(stopped)的进程继续执行. 本信号不能被阻塞. 可以用一个handler来让程序在由stopped状态变为继续执行时完成特定的工作. 例如, 重新显示提示符  
- `SIGSTOP` 停止(stopped)进程的执行. 注意它和terminate以及interrupt的区别:该进程还未结束, 只是暂停执行. 本信号不能被阻塞, 处理或忽略. 
- `SIGTSTP` 停止进程的运行, 但该信号可以被处理和忽略. 用户键入SUSP字符时(通常是Ctrl-Z)发出这个信号  
- `SIGTTIN` 当后台作业要从用户终端读数据时, 该作业中的所有进程会收到SIGTTIN信号. 缺省时这些进程会停止执行.  
- `SIGTTOU` 类似于SIGTTIN, 但在写终端(或修改终端模式)时收到.  
- `SIGURG`  有"紧急"数据或out-of-band数据到达socket时产生.  
- `SIGXCPU`
- `SIGXFSZ`
- `SIGVTALRM` 虚拟时钟信号. 类似于SIGALRM, 但是计算的是该进程占用的CPU时间. 
- `SIGPROF` 类似于SIGALRM/SIGVTALRM, 但包括该进程用的CPU时间以及系统调用的时间. 
- `SIGWINCH` 窗口大小改变时发出.  
- `SIGIO` 文件描述符准备就绪, 可以开始进行输入/输出操作.  
- `SIGPWR`
- `SIGSYS` 非法的系统调用。 

在以上列出的信号中，程序不可捕获、阻塞或忽略的信号有：SIGKILL,SIGSTOP  
  
不能恢复至默认动作的信号有：`SIGILL,SIGTRAP`
  
默认会导致进程流产的信号有：`SIGABRT,SIGBUS,SIGFPE,SIGILL,SIGIOT,SIGQUIT,SIGSEGV,SIGTRAP,SIGXCPU,SIGXFSZ`  
  
默认会导致进程退出的信号有：`SIGALRM,SIGHUP,SIGINT,SIGKILL,SIGPIPE,SIGPOLL,SIGPROF,SIGSYS,SIGTERM,SIGUSR1,SIGUSR2,SIGVTALRM`  
  
默认会导致进程停止的信号有：`SIGSTOP,SIGTSTP,SIGTTIN,SIGTTOU`  
  
默认进程忽略的信号有：`SIGCHLD,SIGPWR,SIGURG,SIGWINCH`  
  
> 此外，SIGIO在SVR4是退出，在4.3BSD中是忽略；SIGCONT在进程挂起时是继续，否则是忽略，不能被阻塞  

- `SIGRTMIN`
- `SIGRTMIN+1`
- `SIGRTMIN+2`
- `SIGRTMIN+3`
- `SIGRTMIN+4`
- `SIGRTMIN+5`
- `SIGRTMIN+6`
- `SIGRTMIN+7`
- `SIGRTMIN+8`
- `SIGRTMIN+9`
- `SIGRTMIN+10`
- `SIGRTMIN+11`
- `SIGRTMIN+12`
- `SIGRTMIN+13`
- `SIGRTMIN+14`
- `SIGRTMIN+15`
- `SIGRTMAX-14`
- `SIGRTMAX-13`
- `SIGRTMAX-12`
- `SIGRTMAX-11`
- `SIGRTMAX-10`
- `SIGRTMAX-9`
- `SIGRTMAX-8`
- `SIGRTMAX-7`
- `SIGRTMAX-6`
- `SIGRTMAX-5`
- `SIGRTMAX-4`
- `SIGRTMAX-3`
- `SIGRTMAX-2`
- `SIGRTMAX-1`
- `SIGRTMAX`

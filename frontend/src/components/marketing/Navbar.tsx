import {Link} from "react-router-dom";

export default function Navbar(){
  return <header className="fixed top-0 z-50 w-full border-b border-white/10 bg-background/80 backdrop-blur-xl"><div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6"><Link to="/" className="flex items-center gap-3"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary font-bold">I</span><span className="font-semibold text-white">IOT-PLATFORM</span></Link><div className="flex items-center gap-3"><Link to="/login" className="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">Login</Link><Link to="/login" className="rounded-lg bg-primary px-5 py-2 text-sm font-medium text-white transition hover:scale-105">Get Started</Link></div></div></header>;
}

import {Link} from "react-router-dom";
import {useAuth} from "../hooks/useAuth";

export default function NotFound(){const {user}=useAuth();const destination=user?.platformRole==="platform_admin"?"/admin/dashboard":user?"/app/dashboard":"/";return <main className="flex min-h-screen items-center justify-center bg-background p-6 text-white"><div className="text-center"><p className="text-sm font-semibold text-primary">404</p><h1 className="mt-2 text-4xl font-bold">Page not found</h1><p className="mt-3 text-gray-400">The requested route does not exist.</p><Link to={destination} className="mt-6 inline-block rounded-lg bg-primary px-5 py-3">Return home</Link></div></main>;}

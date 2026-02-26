

export interface Paginate {
    current_page: number;
    data: Category[];
    first_page_url : string;
    from?: string|number;
    last_page?: string|number;
    last_page_url : string;
    links :  PaginateLinks[];
    next_page_url?: string|null;
    path: string;
    per_page: number;
    prev_page_url?: string|null;
    to?: string|null;
    total : number; 
}


export interface Event {
    id?: number;
    name: string;
    address?: string;
    event_date?: string;
    start_time?: string;
    end_time?: string;
    description?: string;
    lat?: number;
    lng?: number;
    event_image?: string;
    created_at?: string;
    updated_at?: string;
}